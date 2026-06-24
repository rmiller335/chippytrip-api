<?php

namespace Tests\Safe\Jobs;

use App\Jobs\SendNotification;
use App\Models\User;
use App\Models\WatchCallback;
use App\Notifications\Departure;
use Illuminate\Support\Facades\Notification;
use Tests\Safe\TestCase;

// =============================================================================
class SendNotificationTest extends TestCase {
	// =========================================================================
	private function makeCallback(string $eventCode): WatchCallback {
		$wc = WatchCallback::fromApiPayload([
			'alert_id' => 'SUB123',
			'event_code' => $eventCode,
			'summary' => 'UA100 update',
			'flight' => ['fa_flight_id' => 'FA123', 'ident' => 'UA100'],
		]);
		$wc->save();

		return $wc;
	}

	// =========================================================================
	public function test_handle_sends_notification_when_event_code_maps_to_a_class(): void {
		Notification::fake();

		$user = User::factory()->create();
		$callback = $this->makeCallback('departure');

		(new SendNotification($callback, $user))->handle();

		Notification::assertSentTo($user, Departure::class);
	}

	// =========================================================================
	public function test_handle_throws_for_unrecognized_event_code(): void {
		// WatchCallback::notification() always does `new $class(...)`
		// without a class_exists() guard, so an unrecognized event_code
		// throws an Error instead of returning null -- SendNotification's
		// Log::warning() fallback for "no notification" is unreachable.
		$user = User::factory()->create();
		$callback = $this->makeCallback('totally_unknown_event');

		$this->expectException(\Error::class);

		(new SendNotification($callback, $user))->handle();
	}
}
