<?php

namespace Tests\Safe\Jobs;

use App\Jobs\SendNotification;
use App\Models\User;
use App\Models\WatchCallback;
use App\Notifications\Departure;
use App\Notifications\HoldEnd;
use App\Notifications\HoldStart;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Tests\Safe\TestCase;

// =============================================================================
class SendNotificationTest extends TestCase {
	// =========================================================================
	private function makeCallback(string $eventCode): WatchCallback {
		$wc = WatchCallback::fromApiPayload([
			'alert_id' => '1234567',
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
	public function test_handle_sends_hold_notifications(): void {
		Notification::fake();

		$user = User::factory()->create();

		(new SendNotification($this->makeCallback('hold_start'), $user))->handle();
		(new SendNotification($this->makeCallback('hold_end'), $user))->handle();

		Notification::assertSentTo($user, HoldStart::class);
		Notification::assertSentTo($user, HoldEnd::class);
	}

	// =========================================================================
	public function test_handle_warns_and_sends_nothing_for_unrecognized_event_code(): void {
		Notification::fake();
		Log::spy();

		$user = User::factory()->create();
		$callback = $this->makeCallback('totally_unknown_event');

		(new SendNotification($callback, $user))->handle();

		Notification::assertNothingSent();
		Log::shouldHaveReceived('warning')
			->with("SendNotification: no notification for 'totally_unknown_event'", ['callback_id' => $callback->id])
			->once();
	}
}
