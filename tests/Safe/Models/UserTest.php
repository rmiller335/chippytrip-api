<?php

namespace Tests\Safe\Models;

use App\Models\User;
use App\Models\UserChannel;
use NotificationChannels\Pushover\PushoverChannel;
use Tests\Safe\TestCase;

// =============================================================================
class UserTest extends TestCase {
	// =========================================================================
	public function test_route_notification_for_pushover_returns_credentials_key(): void {
		$user = User::factory()->create();

		UserChannel::create([
			'user_id' => $user->id,
			'channel' => PushoverChannel::class,
			'credentials' => ['key' => 'pushover-user-key'],
		]);

		$this->assertSame('pushover-user-key', $user->routeNotificationForPushover());
	}

	// =========================================================================
	public function test_route_notification_for_pushover_returns_null_without_channel(): void {
		$user = User::factory()->create();

		$this->assertNull($user->routeNotificationForPushover());
	}

	// =========================================================================
	public function test_get_channel_ids_attribute(): void {
		$user = User::factory()->create();

		UserChannel::create([
			'user_id' => $user->id,
			'channel' => 'mail',
			'credentials' => [],
		]);

		UserChannel::create([
			'user_id' => $user->id,
			'channel' => PushoverChannel::class,
			'credentials' => ['key' => 'x'],
		]);

		$this->assertEqualsCanonicalizing(
			['mail', PushoverChannel::class],
			$user->channel_ids->all()
		);
	}
}
