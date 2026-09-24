<?php

namespace Tests\Safe\Http\Controllers;

use App\Models\User;
use Tests\Safe\TestCase;

// =============================================================================
class AuthorizerTest extends TestCase {
	// =========================================================================
	public function test_gen_token_returns_a_token_for_valid_credentials(): void {
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

	// =========================================================================
	public function test_gen_token_rejects_invalid_password(): void {
		$user = User::factory()->create(['password' => bcrypt('correct-password')]);

		$response = $this->postJson('/api/sanctum/token', [
			'email' => $user->email,
			'password' => 'wrong-password',
			'device_name' => 'test-device',
		]);

		$response->assertStatus(422);
		$response->assertJsonValidationErrors('email');
	}

	// =========================================================================
	public function test_gen_token_rejects_unknown_email(): void {
		$response = $this->postJson('/api/sanctum/token', [
			'email' => 'nobody@example.com',
			'password' => 'whatever',
			'device_name' => 'test-device',
		]);

		$response->assertStatus(422);
	}

	// =========================================================================
	private function signIn(User $user, string $device = 'device-1', string $password = 'correct-password') {
		return $this->postJson('/api/sanctum/token', [
			'email' => $user->email,
			'password' => $password,
			'device_name' => $device,
		]);
	}

	// =========================================================================
	public function test_signing_in_again_replaces_the_devices_token(): void {
		$user = User::factory()->create(['password' => bcrypt('correct-password')]);

		$old = $this->signIn($user)->getContent();
		$new = $this->signIn($user)->getContent();

		$this->assertNotSame($old, $new);
		$this->assertSame(1, $user->tokens()->count());
		$this->assertNull(\Laravel\Sanctum\PersonalAccessToken::findToken($old));
		$this->assertNotNull(\Laravel\Sanctum\PersonalAccessToken::findToken($new));
	}

	// =========================================================================
	public function test_each_device_keeps_its_own_token(): void {
		$user = User::factory()->create(['password' => bcrypt('correct-password')]);
		$other = User::factory()->create(['password' => bcrypt('correct-password')]);

		$this->signIn($user, 'phone');
		$this->signIn($user, 'tablet');
		$this->signIn($other, 'phone');

		$this->assertSame(2, $user->tokens()->count());
		$this->assertSame(1, $other->tokens()->count());
	}

	// =========================================================================
	public function test_device_names_are_unique_per_user(): void {
		$user = User::factory()->create();
		$user->createToken('phone');

		$this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
		$user->createToken('phone');
	}

	// =========================================================================
	public function test_sign_in_is_rate_limited_per_email(): void {
		config(['auth.login_rate_limit.per_email' => 3]);
		$user = User::factory()->create(['password' => bcrypt('correct-password')]);

		foreach (range(1, 3) as $i) {
			$this->signIn($user, password: 'wrong')->assertStatus(422);
		}

		$response = $this->signIn($user);
		$response->assertStatus(429);
		$response->assertHeader('Retry-After');
		$response->assertJsonStructure(['message']);

		// Other addresses from the same IP still get through.
		$other = User::factory()->create(['password' => bcrypt('correct-password')]);
		$this->signIn($other)->assertStatus(200);
	}

	// =========================================================================
	public function test_sign_in_is_rate_limited_per_ip(): void {
		config(['auth.login_rate_limit.per_ip' => 3]);

		foreach (range(1, 3) as $i) {
			$this->postJson('/api/sanctum/token', [
				'email' => "user{$i}@example.com", 'password' => 'x', 'device_name' => 'd',
			])->assertStatus(422);
		}

		$this->postJson('/api/sanctum/token', [
			'email' => 'user4@example.com', 'password' => 'x', 'device_name' => 'd',
		])->assertStatus(429);
	}
}
