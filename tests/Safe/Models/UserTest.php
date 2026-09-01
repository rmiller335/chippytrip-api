<?php

namespace Tests\Safe\Models;

use App\Models\User;
use App\Models\UserChannel;
use Tests\Safe\TestCase;

// =============================================================================
class UserTest extends TestCase {
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
			'channel' => 'fcm',
			'credentials' => ['key' => 'x'],
		]);

		$this->assertEqualsCanonicalizing(
			['mail', 'fcm'],
			$user->channel_ids->all()
		);
	}
}
