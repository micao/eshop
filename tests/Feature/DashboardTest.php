<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/Dashboard')
                ->has('stats')
                ->has('lowStockVariants')
                ->has('categoriesStats')
            );
    }

    public function test_non_admin_authenticated_users_are_forbidden_from_dashboard()
    {
        $user = User::factory()->create(['role' => User::ROLE_USER]);
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertStatus(403);
    }
}
