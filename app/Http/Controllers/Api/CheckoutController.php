<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ShippingMethod;
use App\Models\Variant;
use App\Services\CartService;
use App\Services\Shipping\ShippingManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    public function __construct(
        protected CartService $cartService,
        protected ShippingManager $shippingManager
    ) {
    }

    /**
     * Get available shipping methods and estimated rates for the selected address.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getShippingRates(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_address_id' => 'required|integer|exists:user_addresses,id',
        ]);

        $address = $request->user()->addresses()->findOrFail($validated['user_address_id']);
        $cart = $this->cartService->getCartDetailsForUser($request->user());

        if (empty($cart['items'])) {
            return response()->json([]);
        }

        $activeMethods = ShippingMethod::where('is_active', true)->get();
        $rates = [];

        foreach ($activeMethods as $method) {
            try {
                $driver = $this->shippingManager->driver($method->gateway_driver);
                $rateResult = $driver->getRates($address->toArray(), $cart['items'], floatval($method->base_price));

                $rates[] = [
                    'shipping_method_id' => $method->id,
                    'name' => $method->name,
                    'carrier' => $method->carrier,
                    'cost' => $rateResult['cost'],
                    'currency' => $rateResult['currency'],
                    'delivery_days' => $rateResult['delivery_days'],
                ];
            } catch (\Exception $e) {
                // Log and skip faulty drivers
                continue;
            }
        }

        return response()->json($rates);
    }

    /**
     * Submit checkout to initialize order, create payment intent, and defer execution to Webhooks.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_address_id' => 'required|integer|exists:user_addresses,id',
            'shipping_method_id' => 'required|integer|exists:shipping_methods,id',
            'payment_method' => 'required|string|in:card,bancontact',
        ]);

        $address = $request->user()->addresses()->findOrFail($validated['user_address_id']);
        $method = ShippingMethod::findOrFail($validated['shipping_method_id']);
        
        $cart = $this->cartService->getCartDetailsForUser($request->user());
        if (empty($cart['items'])) {
            return response()->json(['message' => 'Your shopping cart is empty.'], 422);
        }

        // Validate stock availability
        foreach ($cart['items'] as $item) {
            $variant = Variant::find($item['variant_id']);
            if ($variant && $variant->track_inventory && !$variant->continue_selling_out_of_stock) {
                if ($variant->inventory_quantity < $item['quantity']) {
                    return response()->json([
                        'message' => "Insufficient inventory stock for {$item['name']}. Only {$variant->inventory_quantity} units left."
                    ], 422);
                }
            }
        }

        // Calculate pricing details using the gateway driver
        $driver = $this->shippingManager->driver($method->gateway_driver);
        $rateResult = $driver->getRates($address->toArray(), $cart['items'], floatval($method->base_price));
        $shippingCost = $rateResult['cost'];

        $subtotal = floatval($cart['summary']['subtotal']);
        $grandTotal = $subtotal + $shippingCost;

        // Perform transactional write and order capturing (in pending state)
        $order = DB::transaction(function () use ($request, $address, $method, $driver, $cart, $subtotal, $shippingCost, $grandTotal, $validated) {
            // Register shipment label at carrier network
            $shipment = $driver->createShipment([
                'address' => $address->toArray(),
                'items' => $cart['items'],
            ]);

            // Save order snapshot
            $order = Order::create([
                'user_id' => $request->user()->id,
                'shipping_method_id' => $method->id,
                'payment_method' => $validated['payment_method'],
                'order_number' => 'ORD-' . strtoupper(Str::random(10)),
                'subtotal' => $subtotal,
                'shipping_cost' => $shippingCost,
                'tax' => 0.00,
                'grand_total' => $grandTotal,
                'status' => 'pending',
                'payment_status' => 'pending',
                'tracking_number' => $shipment['tracking_number'] ?? null,
                'shipping_label_url' => $shipment['label_url'] ?? null,
                // Shipping Address snapshot details
                'shipping_name' => $address->recipient_name,
                'shipping_phone' => $address->recipient_phone,
                'shipping_address_line_1' => $address->address_line_1,
                'shipping_address_line_2' => $address->address_line_2,
                'shipping_city' => $address->city,
                'shipping_state_province' => $address->state_province,
                'shipping_postal_code' => $address->postal_code,
                'shipping_country_code' => $address->country_code,
            ]);

            // Save individual items (without deducting stock or clearing cart yet)
            foreach ($cart['items'] as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'variant_id' => $item['variant_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'sku' => $item['sku'],
                ]);
            }

            return $order;
        });

        // Initialize payment intent
        $paymentManager = app(\App\Services\Payment\PaymentManager::class);
        $paymentResult = $paymentManager->driver()->createPaymentIntent($order, $validated['payment_method']);

        $order->update([
            'payment_intent_id' => $paymentResult['transaction_id'] ?? null,
        ]);

        // For local development with 'mock' driver, we auto-succeed the transaction
        if ($paymentManager->getDefaultDriver() === 'mock') {
            DB::transaction(function () use ($order, $cart, $request) {
                $order->update([
                    'status' => 'processing',
                    'payment_status' => 'paid',
                ]);

                // Deduct inventory stock
                foreach ($cart['items'] as $item) {
                    $variant = Variant::find($item['variant_id']);
                    if ($variant && $variant->track_inventory) {
                        $variant->decrement('inventory_quantity', $item['quantity']);
                    }
                }

                // Clear cart
                $this->cartService->clearUserCart($request->user());
            });
        }

        $order->load('items');

        return response()->json([
            'message' => 'Order placed successfully.',
            'order' => $order,
            'client_secret' => $paymentResult['client_secret'] ?? null,
            'redirect_url' => $paymentResult['redirect_url'] ?? null,
        ]);
    }
}
