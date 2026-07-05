<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\User;
use App\Models\Variant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_to_products_api_returns_401(): void
    {
        $response = $this->getJson('/api/products');

        $response->assertStatus(401)
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_authenticated_user_can_retrieve_active_products_list(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Create 3 active products with variants
        $products = Product::factory()->count(3)->create(['status' => 'active']);
        foreach ($products as $product) {
            Variant::factory()->count(2)->create(['product_id' => $product->id]);
        }

        $response = $this->getJson('/api/products');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'description',
                        'summary',
                        'status',
                        'thumbnail',
                        'images',
                        'options',
                        'variants' => [
                            '*' => [
                                'id',
                                'name',
                                'sku',
                                'barcode',
                                'price',
                                'compare_at_price',
                                'inventory_quantity',
                                'track_inventory',
                                'continue_selling_out_of_stock',
                                'weight',
                                'weight_unit',
                                'dimensions' => ['width', 'height', 'depth', 'unit'],
                                'options',
                            ]
                        ]
                    ]
                ],
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'from', 'last_page', 'path', 'per_page', 'to', 'total']
            ])
            ->assertJsonCount(3, 'data');
    }

    public function test_products_api_excludes_non_active_products(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Create 1 active product
        Product::factory()->create(['status' => 'active']);

        // Create 1 draft and 1 archived product
        Product::factory()->create(['status' => 'draft']);
        Product::factory()->create(['status' => 'archived']);

        $response = $this->getJson('/api/products');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_products_api_excludes_cost_price_from_variants(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $product = Product::factory()->create(['status' => 'active']);
        Variant::factory()->create([
            'product_id' => $product->id,
            'cost_price' => 150.00
        ]);

        $response = $this->getJson('/api/products');

        $response->assertStatus(200)
            ->assertJsonMissingPath('data.0.variants.0.cost_price');
    }

    public function test_products_api_paginates_correctly(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Create 15 active products (should paginate 12 per page)
        Product::factory()->count(15)->create(['status' => 'active']);

        $response = $this->getJson('/api/products');

        $response->assertStatus(200)
            ->assertJsonCount(12, 'data')
            ->assertJsonPath('meta.total', 15)
            ->assertJsonPath('meta.per_page', 12)
            ->assertJsonPath('meta.current_page', 1);

        // Query second page
        $responsePage2 = $this->getJson('/api/products?page=2');
        $responsePage2->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.current_page', 2);
    }
}
