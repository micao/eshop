<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Models\UserAddress;
use App\Models\Variant;
use App\Services\CartService;
use App\Services\Payment\PaymentManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class PaymentApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Enforce the Stripe driver during payment tests
        Config::set('payment.default', 'stripe');
        Config::set('services.stripe.secret_key', 'sk_test_mock_key');
        Config::set('services.stripe.webhook_secret', ''); // empty webhook secret bypasses signature checks in mock testing

        \Illuminate\Support\Facades\Http::fake([
            'api.stripe.com/v1/payment_intents' => \Illuminate\Support\Facades\Http::response([
                'id' => 'pi_mock_1234567890',
                'client_secret' => 'pi_mock_1234567890_secret_123456',
            ], 200)
        ]);
    }

    public function test_checkout_initializes_pending_order_and_stripe_session()
    {
        $user = User::factory()->create();
        $address = UserAddress::create([
            'user_id' => $user->id,
            'recipient_name' => 'John Payment',
            'recipient_phone' => '+32490111111',
            'address_line_1' => 'Rue du Midi 1',
            'city' => 'Brussels',
            'postal_code' => '1000',
            'country_code' => 'BE',
            'is_default' => true,
        ]);

        $shippingMethod = ShippingMethod::create([
            'name' => 'bpost Standard',
            'carrier' => 'bpost',
            'gateway_driver' => 'flat_rate',
            'base_price' => 5.00,
            'is_active' => true,
        ]);

        $product = Product::factory()->create();
        $variant = Variant::create([
            'product_id' => $product->id,
            'sku' => 'STR-SKU-1',
            'name' => 'Standard Item',
            'price' => 20.00,
            'inventory_quantity' => 10,
            'track_inventory' => true,
        ]);

        // Add item to cart
        $cartService = app(CartService::class);
        $cartService->addItemToUserCart($user, $variant->id, 2);

        // 1. Submit Checkout with Stripe Card
        $response = $this->actingAs($user)
            ->postJson('/api/checkout', [
                'user_address_id' => $address->id,
                'shipping_method_id' => $shippingMethod->id,
                'payment_method' => 'card',
            ]);

        $response->assertOk()
            ->assertJsonPath('order.status', 'pending')
            ->assertJsonPath('order.payment_status', 'pending')
            ->assertJsonPath('order.payment_method', 'card');

        $this->assertNotNull($response->json('client_secret'));

        $intentId = $response->json('order.payment_intent_id');
        $this->assertNotNull($intentId);

        // 2. Verify stock is NOT deducted yet
        $this->assertEquals(10, $variant->fresh()->inventory_quantity);

        // 3. Verify cart is NOT cleared yet
        $cart = $cartService->getCartDetailsForUser($user);
        $this->assertCount(1, $cart['items']);

        // 4. Simulate Stripe Webhook callback event
        $webhookResponse = $this->postJson('/api/webhooks/stripe', [
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => $intentId,
                ]
            ]
        ]);

        $webhookResponse->assertOk();

        // 5. Verify order is now Paid and Processing
        $order = Order::where('payment_intent_id', $intentId)->first();
        $this->assertEquals('processing', $order->status);
        $this->assertEquals('paid', $order->payment_status);

        // 6. Verify stock has now been deducted
        $this->assertEquals(8, $variant->fresh()->inventory_quantity);

        // 7. Verify cart has now been cleared
        $cart = $cartService->getCartDetailsForUser($user);
        $this->assertCount(0, $cart['items']);
    }
}
