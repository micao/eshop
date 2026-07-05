<?php

namespace Tests\Feature\Web;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderWebControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $response = $this->get('/admin/orders');
        $response->assertRedirect('/login');
    }

    public function test_non_admin_authenticated_users_are_forbidden_from_admin_orders_page(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_USER]);
        $response = $this->actingAs($user)->get('/admin/orders');
        $response->assertStatus(403);
    }

    public function test_authenticated_admin_can_visit_orders_index_page(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $customer = User::factory()->create(['role' => User::ROLE_USER]);

        // Create 2 test orders
        Order::create([
            'user_id' => $customer->id,
            'order_number' => 'ORD-99991',
            'subtotal' => 100.00,
            'grand_total' => 100.00,
            'status' => 'processing',
            'payment_status' => 'paid',
            'shipping_name' => 'Alice Customer',
            'shipping_phone' => '123456789',
            'shipping_address_line_1' => '123 St',
            'shipping_city' => 'Brussels',
            'shipping_postal_code' => '1000',
            'shipping_country_code' => 'BE',
        ]);

        Order::create([
            'user_id' => $customer->id,
            'order_number' => 'ORD-99992',
            'subtotal' => 200.00,
            'grand_total' => 205.00,
            'status' => 'pending',
            'payment_status' => 'pending',
            'shipping_name' => 'Bob Customer',
            'shipping_phone' => '987654321',
            'shipping_address_line_1' => '456 Ave',
            'shipping_city' => 'Ghent',
            'shipping_postal_code' => '9000',
            'shipping_country_code' => 'BE',
        ]);

        // Access as Admin
        $response = $this->actingAs($admin)->get('/admin/orders');

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('admin/orders/Index')
                ->has('orders.data', 2)
            );
    }

    public function test_authenticated_admin_can_filter_orders_by_fulfillment_status(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $customer = User::factory()->create(['role' => User::ROLE_USER]);

        Order::create([
            'user_id' => $customer->id,
            'order_number' => 'ORD-99991',
            'subtotal' => 100.00,
            'grand_total' => 100.00,
            'status' => 'completed',
            'payment_status' => 'paid',
            'shipping_name' => 'Alice Customer',
            'shipping_phone' => '123456789',
            'shipping_address_line_1' => '123 St',
            'shipping_city' => 'Brussels',
            'shipping_postal_code' => '1000',
            'shipping_country_code' => 'BE',
        ]);

        Order::create([
            'user_id' => $customer->id,
            'order_number' => 'ORD-99992',
            'subtotal' => 200.00,
            'grand_total' => 205.00,
            'status' => 'pending',
            'payment_status' => 'pending',
            'shipping_name' => 'Bob Customer',
            'shipping_phone' => '987654321',
            'shipping_address_line_1' => '456 Ave',
            'shipping_city' => 'Ghent',
            'shipping_postal_code' => '9000',
            'shipping_country_code' => 'BE',
        ]);

        // Filter status = completed
        $response = $this->actingAs($admin)->get('/admin/orders?status=completed');

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('admin/orders/Index')
                ->has('orders.data', 1)
                ->where('orders.data.0.order_number', 'ORD-99991')
            );
    }
}
