<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddressApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_addresses()
    {
        $this->getJson('/api/addresses')->assertStatus(401);
    }

    public function test_user_can_manage_addresses()
    {
        $user = User::factory()->create();

        // 1. Store address
        $response = $this->actingAs($user)
            ->postJson('/api/addresses', [
                'recipient_name' => 'John Doe',
                'recipient_phone' => '+32490123456',
                'address_line_1' => 'Rue de la Loi 16',
                'city' => 'Brussels',
                'postal_code' => '1000',
                'country_code' => 'BE',
                'is_default' => true,
            ]);

        $response->assertOk()
            ->assertJsonPath('address.recipient_name', 'John Doe')
            ->assertJsonPath('address.is_default', true);

        $addressId = $response->json('address.id');

        // 2. List addresses
        $response = $this->actingAs($user)->getJson('/api/addresses');
        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $addressId);

        // 3. Delete address
        $this->actingAs($user)
            ->deleteJson("/api/addresses/{$addressId}")
            ->assertOk();

        $this->actingAs($user)->getJson('/api/addresses')
            ->assertOk()
            ->assertJsonCount(0);
    }
}
