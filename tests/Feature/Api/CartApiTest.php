<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\User;
use App\Models\Variant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_post_for_cart_details()
    {
        $product = Product::factory()->create();
        $variant = Variant::create([
            'product_id' => $product->id,
            'sku' => 'TEST-SKU-1',
            'name' => 'Default Variant',
            'price' => 29.99,
            'inventory_quantity' => 10,
            'track_inventory' => true,
        ]);

        $response = $this->postJson('/api/cart/details', [
            'items' => [
                ['variant_id' => $variant->id, 'quantity' => 2]
            ]
        ]);

        $response->assertOk()
            ->assertJsonPath('summary.item_count', 1)
            ->assertJsonPath('summary.subtotal', 59.98)
            ->assertJsonPath('items.0.sku', 'TEST-SKU-1');
    }

    public function test_unauthenticated_cart_endpoint_returns_401()
    {
        $this->getJson('/api/cart')->assertStatus(401);
    }

    public function test_authenticated_user_can_manage_cart()
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $variant = Variant::create([
            'product_id' => $product->id,
            'sku' => 'TEST-SKU-2',
            'name' => 'Default Variant 2',
            'price' => 19.99,
            'inventory_quantity' => 5,
            'track_inventory' => true,
        ]);

        // Add to cart
        $response = $this->actingAs($user)
            ->postJson('/api/cart', [
                'variant_id' => $variant->id,
                'quantity' => 2,
            ]);

        $response->assertOk();

        // Get cart
        $response = $this->actingAs($user)->getJson('/api/cart');
        $response->assertOk()
            ->assertJsonPath('summary.item_count', 1)
            ->assertJsonPath('items.0.quantity', 2)
            ->assertJsonPath('items.0.price', 19.99);

        $cartItemId = $response->json('items.0.cart_item_id');

        // Update quantity
        $this->actingAs($user)
            ->putJson("/api/cart/items/{$cartItemId}", [
                'quantity' => 3
            ])
            ->assertOk();

        // Check stock limit validation
        $this->actingAs($user)
            ->putJson("/api/cart/items/{$cartItemId}", [
                'quantity' => 6
            ])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Cannot update quantity. Only 5 units available.']);

        // Remove item
        $this->actingAs($user)
            ->deleteJson("/api/cart/items/{$cartItemId}")
            ->assertOk();

        $this->actingAs($user)
            ->getJson('/api/cart')
            ->assertOk()
            ->assertJsonPath('summary.item_count', 0);
    }

    public function test_authenticated_user_can_merge_guest_cart()
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $variant = Variant::create([
            'product_id' => $product->id,
            'sku' => 'TEST-SKU-3',
            'name' => 'Default Variant 3',
            'price' => 10.00,
            'inventory_quantity' => 10,
            'track_inventory' => true,
        ]);

        $this->actingAs($user)
            ->postJson('/api/cart/merge', [
                'items' => [
                    ['variant_id' => $variant->id, 'quantity' => 3]
                ]
            ])
            ->assertOk();

        $this->actingAs($user)
            ->getJson('/api/cart')
            ->assertOk()
            ->assertJsonPath('items.0.quantity', 3);
    }
}
