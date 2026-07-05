<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Category;
use App\Models\Product;
use App\Models\Variant;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GraphQLApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_graphql_unauthenticated_request_returns_401(): void
    {
        $query = 'query { usersCarts { name } }';
        $response = $this->postJson('/api/graphql', ['query' => $query]);
        $response->assertStatus(401);
    }

    public function test_graphql_user_can_only_query_own_orders_and_cart(): void
    {
        // Arrange
        $user1 = User::factory()->create(['name' => 'John User', 'email' => 'john@example.com', 'role' => User::ROLE_USER]);
        $user2 = User::factory()->create(['name' => 'Jane Admin', 'email' => 'jane@example.com', 'role' => User::ROLE_ADMIN]);

        $cat = Category::create(['name' => 'Tech', 'slug' => 'tech']);
        $product = Product::create([
            'category_id' => $cat->id,
            'name' => 'iPhone',
            'slug' => 'iphone',
            'status' => 'active',
        ]);
        $variant = Variant::create([
            'product_id' => $product->id,
            'name' => '128GB',
            'sku' => 'IPH-128',
            'price' => 799.00,
            'inventory_quantity' => 10,
        ]);

        // Cart for user 1
        $cart = Cart::create(['user_id' => $user1->id]);
        CartItem::create([
            'cart_id' => $cart->id,
            'variant_id' => $variant->id,
            'quantity' => 2,
        ]);

        // Cart for user 2
        $cart2 = Cart::create(['user_id' => $user2->id]);
        CartItem::create([
            'cart_id' => $cart2->id,
            'variant_id' => $variant->id,
            'quantity' => 1,
        ]);

        // Authenticate as John User (standard user)
        $this->actingAs($user1);

        // Act & Assert usersCarts (Should only return user1)
        $query = 'query { usersCarts { name email cart { totalPrice items { quantity } } } }';
        $response = $this->postJson('/api/graphql', ['query' => $query]);

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.usersCarts')
            ->assertJsonPath('data.usersCarts.0.name', 'John User');
    }

    public function test_graphql_admin_can_query_all_users_orders_and_carts(): void
    {
        // Arrange
        $user1 = User::factory()->create(['name' => 'John User', 'email' => 'john@example.com', 'role' => User::ROLE_USER]);
        $user2 = User::factory()->create(['name' => 'Jane Admin', 'email' => 'jane@example.com', 'role' => User::ROLE_ADMIN]);

        $cat = Category::create(['name' => 'Tech', 'slug' => 'tech']);
        $product = Product::create([
            'category_id' => $cat->id,
            'name' => 'iPhone',
            'slug' => 'iphone',
            'status' => 'active',
        ]);
        $variant = Variant::create([
            'product_id' => $product->id,
            'name' => '128GB',
            'sku' => 'IPH-128',
            'price' => 799.00,
            'inventory_quantity' => 10,
        ]);

        // Cart for user 1
        $cart = Cart::create(['user_id' => $user1->id]);
        CartItem::create([
            'cart_id' => $cart->id,
            'variant_id' => $variant->id,
            'quantity' => 2,
        ]);

        // Cart for user 2
        $cart2 = Cart::create(['user_id' => $user2->id]);
        CartItem::create([
            'cart_id' => $cart2->id,
            'variant_id' => $variant->id,
            'quantity' => 1,
        ]);

        // Authenticate as Jane Admin
        $this->actingAs($user2);

        // Act & Assert usersCarts (Should return all 2 users)
        $query = 'query { usersCarts { name email cart { totalPrice items { quantity } } } }';
        $response = $this->postJson('/api/graphql', ['query' => $query]);

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data.usersCarts');
    }
}
