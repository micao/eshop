<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_api_token_with_correct_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/tokens', [
            'email' => 'test@example.com',
            'password' => 'password123',
            'device_name' => 'Test iPhone',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'token',
                'user' => ['id', 'name', 'email']
            ])
            ->assertJsonPath('user.email', 'test@example.com');
    }

    public function test_token_creation_requires_all_fields(): void
    {
        $response = $this->postJson('/api/tokens', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password', 'device_name']);
    }

    public function test_token_creation_fails_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Wrong password
        $response = $this->postJson('/api/tokens', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
            'device_name' => 'Test iPhone',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email'])
            ->assertJsonMissingPath('token');

        // Unregistered email
        $response = $this->postJson('/api/tokens', [
            'email' => 'nonexistent@example.com',
            'password' => 'password123',
            'device_name' => 'Test iPhone',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_token_creation_is_rate_limited(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Hit the endpoint 5 times with wrong credentials
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/tokens', [
                'email' => 'test@example.com',
                'password' => 'wrong-password',
                'device_name' => 'Test iPhone',
            ])->assertStatus(422);
        }

        // The 6th attempt should return 429 Too Many Requests due to rate limiting
        $this->postJson('/api/tokens', [
            'email' => 'test@example.com',
            'password' => 'password123',
            'device_name' => 'Test iPhone',
        ])->assertStatus(429);
    }
}
