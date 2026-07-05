<?php

namespace Tests\Feature\Web;

use App\Models\Product;
use App\Models\User;
use App\Models\Variant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductWebControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $response = $this->get('/admin/products');
        $response->assertRedirect('/login');

        $product = Product::factory()->create();
        $responsePut = $this->put("/admin/products/{$product->id}", [
            'name' => 'New Title',
            'status' => 'active'
        ]);
        $responsePut->assertRedirect('/login');

        $responseDelete = $this->delete("/admin/products/{$product->id}");
        $responseDelete->assertRedirect('/login');
    }

    public function test_non_admin_authenticated_users_are_forbidden_from_admin_pages(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_USER]);

        $responseIndex = $this->actingAs($user)->get('/admin/products');
        $responseIndex->assertStatus(403);

        $product = Product::factory()->create();
        $responsePut = $this->actingAs($user)->put("/admin/products/{$product->id}", [
            'name' => 'New Title',
            'status' => 'active'
        ]);
        $responsePut->assertStatus(403);
    }

    public function test_authenticated_admin_can_visit_products_index_page(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $products = Product::factory()->count(3)->create(['status' => 'active']);
        foreach ($products as $product) {
            Variant::factory()->count(2)->create(['product_id' => $product->id]);
        }

        $response = $this->actingAs($user)->get('/admin/products');

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('admin/products/Index')
                ->has('products.data', 3)
            );
    }

    public function test_authenticated_admin_can_update_product(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $product = Product::factory()->create([
            'name' => 'Original Name',
            'slug' => 'original-slug',
            'status' => 'draft'
        ]);

        $response = $this->actingAs($user)->put("/admin/products/{$product->id}", [
            'name' => 'Updated Name',
            'status' => 'active',
            'summary' => 'New Summary',
            'description' => 'New Description'
        ]);

        $response->assertRedirect();
        
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Updated Name',
            'status' => 'active',
            'summary' => 'New Summary',
            'description' => 'New Description'
        ]);
    }

    public function test_authenticated_admin_can_soft_delete_product(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $product = Product::factory()->create();

        $response = $this->actingAs($user)->delete("/admin/products/{$product->id}");

        $response->assertRedirect();

        $this->assertSoftDeleted('products', [
            'id' => $product->id
        ]);
    }
}
