<?php

namespace Tests\Feature\Web;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Variant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_renders_successfully_with_props(): void
    {
        // Arrange
        $category = Category::create(['name' => 'Tech', 'slug' => 'tech']);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Wired Keyboard',
            'slug' => 'wired-keyboard',
            'status' => 'active',
        ]);
        Variant::factory()->create([
            'product_id' => $product->id,
            'price' => 29.99
        ]);

        // Act
        $response = $this->get('/');

        // Assert
        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Welcome')
                ->has('categories', 1)
                ->has('newArrivals', 1)
            );
    }

    public function test_catalog_page_renders_and_filters_by_category(): void
    {
        // Arrange
        $cat1 = Category::create(['name' => 'Audio', 'slug' => 'audio']);
        $cat2 = Category::create(['name' => 'Computers', 'slug' => 'computers']);

        $prod1 = Product::factory()->create(['category_id' => $cat1->id, 'slug' => 'headphones', 'status' => 'active']);
        $prod2 = Product::factory()->create(['category_id' => $cat2->id, 'slug' => 'laptop', 'status' => 'active']);

        Variant::factory()->create(['product_id' => $prod1->id]);
        Variant::factory()->create(['product_id' => $prod2->id]);

        // Act & Assert (Category 1 filter)
        $response = $this->get('/catalog?category=audio');
        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('catalog/Index')
                ->has('products.data', 1)
                ->where('products.data.0.slug', 'headphones')
            );
    }

    public function test_catalog_page_filters_by_brand(): void
    {
        // Arrange
        $brand1 = Brand::create(['name' => 'Apple', 'slug' => 'apple']);
        $brand2 = Brand::create(['name' => 'Sony', 'slug' => 'sony']);

        $prod1 = Product::factory()->create(['brand_id' => $brand1->id, 'slug' => 'iphone', 'status' => 'active']);
        $prod2 = Product::factory()->create(['brand_id' => $brand2->id, 'slug' => 'walkman', 'status' => 'active']);

        Variant::factory()->create(['product_id' => $prod1->id]);
        Variant::factory()->create(['product_id' => $prod2->id]);

        // Act & Assert
        $response = $this->get('/catalog?brand=apple');
        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('catalog/Index')
                ->has('products.data', 1)
                ->where('products.data.0.slug', 'iphone')
            );
    }

    public function test_catalog_search_filters_by_keywords(): void
    {
        // Arrange
        $prod1 = Product::factory()->create(['name' => 'Cool Speaker', 'status' => 'active']);
        $prod2 = Product::factory()->create(['name' => 'Gamer Mouse', 'status' => 'active']);
        
        Variant::factory()->create(['product_id' => $prod1->id]);
        Variant::factory()->create(['product_id' => $prod2->id]);

        // Act
        $response = $this->get('/catalog?search=Speaker');

        // Assert
        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('catalog/Index')
                ->has('products.data', 1)
                ->where('products.data.0.name', 'Cool Speaker')
            );
    }

    public function test_product_detail_page_renders_successfully(): void
    {
        // Arrange
        $product = Product::factory()->create([
            'name' => 'Fujifilm Camera',
            'slug' => 'fujifilm-camera',
            'status' => 'active'
        ]);
        Variant::factory()->create(['product_id' => $product->id]);

        // Act & Assert (Exists)
        $response = $this->get('/catalog/products/fujifilm-camera');
        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('catalog/Show')
                ->where('product.name', 'Fujifilm Camera')
            );

        // Act & Assert (Not Exists)
        $response404 = $this->get('/catalog/products/non-existent-slug');
        $response404->assertStatus(404);
    }

    public function test_cart_page_renders_successfully(): void
    {
        $response = $this->get('/cart');
        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('catalog/Cart')
            );
    }

    public function test_guest_cannot_access_checkout_page(): void
    {
        $this->get('/checkout')->assertRedirect('/login');
    }

    public function test_authenticated_user_can_access_checkout_page(): void
    {
        $user = \App\Models\User::factory()->create();

        $response = $this->actingAs($user)->get('/checkout');
        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('catalog/Checkout')
            );
    }

    public function test_checkout_success_page_renders_successfully(): void
    {
        $user = \App\Models\User::factory()->create();
        $order = \App\Models\Order::create([
            'user_id' => $user->id,
            'order_number' => 'ORD-TEST1234',
            'subtotal' => 50,
            'shipping_cost' => 0,
            'grand_total' => 50,
            'status' => 'processing',
            'shipping_name' => 'Jane Smith',
            'shipping_phone' => '+32490999999',
            'shipping_address_line_1' => 'Avenue Louise 222',
            'shipping_city' => 'Brussels',
            'shipping_postal_code' => '1050',
            'shipping_country_code' => 'BE',
        ]);

        $response = $this->actingAs($user)->get('/checkout/success?order_number=ORD-TEST1234');
        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('catalog/CheckoutSuccess')
                ->where('order.order_number', 'ORD-TEST1234')
            );
    }
}
