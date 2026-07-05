<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Models\UserAddress;
use App\Models\Variant;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_checkout()
    {
        $this->postJson('/api/checkout')->assertStatus(401);
    }

    public function test_user_can_retrieve_rates_and_checkout_successfully()
    {
        $user = User::factory()->create();
        $address = UserAddress::create([
            'user_id' => $user->id,
            'recipient_name' => 'Jane Smith',
            'recipient_phone' => '+32490999999',
            'address_line_1' => 'Avenue Louise 222',
            'city' => 'Brussels',
            'postal_code' => '1050',
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
            'sku' => 'CHKT-SKU-1',
            'name' => '100g / Regular',
            'price' => 15.00,
            'inventory_quantity' => 10,
            'track_inventory' => true,
        ]);

        // Add to cart
        $cartService = app(CartService::class);
        $cartService->addItemToUserCart($user, $variant->id, 2);

        // 1. Get Shipping Rates
        $response = $this->actingAs($user)
            ->getJson("/api/checkout/shipping-rates?user_address_id={$address->id}");

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.cost', 5); // 15.00 * 2 = 30.00 (< 50, BE address, base price = 5)

        // 2. Perform Checkout
        $response = $this->actingAs($user)
            ->postJson('/api/checkout', [
                'user_address_id' => $address->id,
                'shipping_method_id' => $shippingMethod->id,
                'payment_method' => 'card',
            ]);

        $response->assertOk()
            ->assertJsonPath('order.subtotal', 30)
            ->assertJsonPath('order.shipping_cost', 5)
            ->assertJsonPath('order.grand_total', 35)
            ->assertJsonPath('order.shipping_name', 'Jane Smith')
            ->assertJsonPath('order.shipping_address_line_1', 'Avenue Louise 222')
            ->assertJsonPath('order.shipping_country_code', 'BE')
            ->assertJsonPath('order.status', 'processing')
            ->assertJsonCount(1, 'order.items');

        $this->assertNotNull($response->json('order.tracking_number'));
        $this->assertNotNull($response->json('order.shipping_label_url'));

        // 3. Verify stock was deducted
        $this->assertEquals(8, $variant->fresh()->inventory_quantity);

        // 4. Verify cart was cleared
        $cart = $cartService->getCartDetailsForUser($user);
        $this->assertCount(0, $cart['items']);
    }

    public function test_checkout_applies_free_shipping_above_threshold()
    {
        $user = User::factory()->create();
        $address = UserAddress::create([
            'user_id' => $user->id,
            'recipient_name' => 'Jane Smith',
            'recipient_phone' => '+32490999999',
            'address_line_1' => 'Avenue Louise 222',
            'city' => 'Brussels',
            'postal_code' => '1050',
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
            'sku' => 'CHKT-SKU-2',
            'name' => 'Bulk Box',
            'price' => 60.00,
            'inventory_quantity' => 5,
            'track_inventory' => true,
        ]);

        // Add to cart
        $cartService = app(CartService::class);
        $cartService->addItemToUserCart($user, $variant->id, 1); // 60.00 (> 50 threshold)

        // Checkout
        $response = $this->actingAs($user)
            ->postJson('/api/checkout', [
                'user_address_id' => $address->id,
                'shipping_method_id' => $shippingMethod->id,
                'payment_method' => 'card',
            ]);

        $response->assertOk()
            ->assertJsonPath('order.subtotal', 60)
            ->assertJsonPath('order.shipping_cost', 0)
            ->assertJsonPath('order.grand_total', 60);
    }
}
