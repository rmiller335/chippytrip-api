<?php

namespace Tests\Safe\Http\Controllers;

use App\Models\User;
use App\Models\UserChannel;
use App\Notifications\Channels\FcmChannel;
use Tests\Safe\TestCase;

// =============================================================================
class FcmTokenControllerTest extends TestCase {
	// =========================================================================
	private function registerToken(User $user, string $deviceId, string $token): void {
		$user->channels()->create([
			'channel' => FcmChannel::class,
			'identifier' => $deviceId,
			'credentials' => ['token' => $token],
		]);
	}

	// =========================================================================
	public function test_destroy_removes_only_that_devices_token(): void {
		$user = User::factory()->create();
		$this->registerToken($user, 'phone', 'token-phone');
		$this->registerToken($user, 'tablet', 'token-tablet');

		$response = $this->actingAs($user, 'sanctum')
			->deleteJson('/api/fcm-token', ['device_id' => 'phone']);

		$response->assertNoContent();
		$this->assertSame(['tablet'], $user->channels()->pluck('identifier')->all());
	}

	// =========================================================================
	public function test_destroy_does_not_touch_other_users_tokens(): void {
		$user = User::factory()->create();
		$other = User::factory()->create();
		$this->registerToken($other, 'phone', 'token-other');

		$this->actingAs($user, 'sanctum')
			->deleteJson('/api/fcm-token', ['device_id' => 'phone'])
			->assertNoContent();

		$this->assertSame(1, $other->channels()->count());
	}

	// =========================================================================
	public function test_destroy_is_idempotent(): void {
		$user = User::factory()->create();

		$this->actingAs($user, 'sanctum')
			->deleteJson('/api/fcm-token', ['device_id' => 'phone'])
			->assertNoContent();
	}

	// =========================================================================
	public function test_destroy_requires_device_id(): void {
		$user = User::factory()->create();

		$this->actingAs($user, 'sanctum')
			->deleteJson('/api/fcm-token')
			->assertUnprocessable();
	}

	// =========================================================================
	public function test_destroy_requires_auth(): void {
		$this->deleteJson('/api/fcm-token', ['device_id' => 'phone'])
			->assertUnauthorized();

		$this->assertSame(0, UserChannel::count());
	}
}
