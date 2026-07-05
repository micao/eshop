<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Variant;
use App\Services\CartService;
use App\Services\Payment\PaymentManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
    public function __construct(
        protected PaymentManager $paymentManager,
        protected CartService $cartService
    ) {}

    /**
     * Handle incoming Stripe webhook notifications.
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $this->paymentManager->driver()->verifyWebhook($request);

        if (! $payload) {
            return response()->json(['message' => 'Invalid webhook signature.'], 400);
        }

        $event = $payload['event'] ?? '';
        $paymentIntentId = $payload['payment_intent_id'] ?? '';

        if (empty($paymentIntentId)) {
            return response()->json(['message' => 'Payment Intent ID missing in callback.'], 400);
        }

        Log::info("Processing Stripe webhook: {$event} for payment intent {$paymentIntentId}");

        if ($event === 'payment_intent.succeeded') {
            $order = Order::where('payment_intent_id', $paymentIntentId)->first();

            if (! $order) {
                Log::warning("Order not found for Stripe payment intent: {$paymentIntentId}");

                return response()->json(['message' => 'Order not found.'], 404);
            }

            if ($order->payment_status === 'paid') {
                return response()->json(['message' => 'Order already processed.']);
            }

            // DB transaction to safely update order, deduct inventory, and flush cart
            DB::transaction(function () use ($order) {
                $order->update([
                    'status' => 'processing',
                    'payment_status' => 'paid',
                ]);

                // Deduct stock for each variant purchased
                $order->load('items');
                foreach ($order->items as $item) {
                    $variant = Variant::find($item->variant_id);
                    if ($variant && $variant->track_inventory) {
                        $variant->decrement('inventory_quantity', $item->quantity);
                    }
                }

                // Clear customer cart
                $this->cartService->clearUserCart($order->user);
            });

            Log::info("Order {$order->order_number} successfully marked as PAID via webhook.");
        }

        return response()->json(['status' => 'success']);
    }
}
