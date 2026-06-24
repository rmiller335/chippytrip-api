<?php

namespace Tests\Safe\Http\Controllers;

use App\Models\User;
use Tests\Safe\TestCase;

class AuthorizerTest extends TestCase
{
    public function test_gen_token_returns_a_token_for_valid_credentials(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct-password')]);

        $response = $this->postJson('/api/sanctum/token', [
            'email' => $user->email,
            'password' => 'correct-password',
            'device_name' => 'test-device',
        ]);

        // genToken() returns the plain token string directly, not a JSON body.
        $response->assertStatus(200);
        $this->assertNotEmpty($response->getContent());
    }

    public function test_gen_token_rejects_invalid_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct-password')]);

        $response = $this->postJson('/api/sanctum/token', [
            'email' => $user->email,
            'password' => 'wrong-password',
            'device_name' => 'test-device',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
    }

    public function test_gen_token_rejects_unknown_email(): void
    {
        $response = $this->postJson('/api/sanctum/token', [
            'email' => 'nobody@example.com',
            'password' => 'whatever',
            'device_name' => 'test-device',
        ]);

        $response->assertStatus(422);
    }
}
