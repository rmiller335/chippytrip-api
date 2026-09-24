<?php

namespace Tests\Safe\Http\Controllers;

use App\Jobs\NotificationsIndex;
use App\Jobs\SendNotification;
use App\Models\User;
use App\Models\Watch;
use App\Models\WatchCallback;
use App\Notifications\Arrival;
use App\Notifications\Departure;
use Carbon\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Tests\Safe\TestCase;

// =============================================================================
class WatchCallbackTest extends TestCase {
	// =========================================================================
	private function payload(string $subscriptionId, string $departureDate, array $overrides = []): array {
		return array_merge([
			'alert_id' => $subscriptionId,
			'event_code' => 'departure',
			'summary' => 'UA100 departed',
			'flight' => [
				'fa_flight_id' => 'FA123',
				'ident' => 'UA100',
				'scheduled_out' => $departureDate . 'T14:00:00Z',
				'actual_out' => $departureDate . 'T14:05:00Z',
			],
		], $overrides);
	}

	// =========================================================================
	public function test_callback_dispatches_notifications_when_departure_date_matches(): void {
		Bus::fake();

		$today = Carbon::now()->toDateString();
		$flight = $this->makeFlight(['departure_date' => $today]);
		$watch = Watch::create([
			'flight_id' => $flight->id,
			'subscription_id' => 'SUB123',
			'secret' => 'topsecret',
			'enabled' => true,
		]);
		$user = User::factory()->create();
		$user->listeners()->create(['watch_id' => $watch->id, 'travelers' => '1']);

		$response = $this->postJson('/api/watch-callback?s=topsecret', $this->payload('SUB123', $today));

		$response->assertStatus(200);
		$this->assertDatabaseHas('watch_callbacks', ['alert_id' => 'SUB123', 'watch_id' => $watch->id]);

		Bus::assertChained([SendNotification::class, NotificationsIndex::class]);
	}

	// =========================================================================
	public function test_callback_does_not_dispatch_when_departure_date_does_not_match(): void {
		Bus::fake();

		$flight = $this->makeFlight(['departure_date' => Carbon::now()->toDateString()]);
		$watch = Watch::create([
			'flight_id' => $flight->id,
			'subscription_id' => 'SUB123',
			'secret' => 'topsecret',
			'enabled' => true,
		]);
		$user = User::factory()->create();
		$user->listeners()->create(['watch_id' => $watch->id, 'travelers' => '1']);

		// Payload claims a departure date that doesn't match the flight on file.
		$mismatchedDate = Carbon::now()->addDays(5)->toDateString();

		$response = $this->postJson('/api/watch-callback?s=topsecret', $this->payload('SUB123', $mismatchedDate));

		$response->assertStatus(200);
		Bus::assertNothingDispatched();
	}

	// =========================================================================
	public function test_callback_rejects_wrong_secret(): void {
		Bus::fake();

		$flight = $this->makeFlight();
		Watch::create([
			'flight_id' => $flight->id,
			'subscription_id' => 'SUB123',
			'secret' => 'topsecret',
			'enabled' => true,
		]);

		$response = $this->postJson('/api/watch-callback?s=wrong-secret', $this->payload('SUB123', Carbon::now()->toDateString()));

		$response->assertStatus(403);
		$this->assertDatabaseCount('watch_callbacks', 0);
	}

	// =========================================================================
	public function test_callback_rejects_unknown_subscription(): void {
		$response = $this->postJson('/api/watch-callback?s=anything', $this->payload('UNKNOWN-SUB', Carbon::now()->toDateString()));

		$response->assertStatus(403);
		$this->assertDatabaseCount('watch_callbacks', 0);
	}

	// -------------------------------------------------------------------------
	// End to end: callback -> SendNotification -> user notified. The queue
	// runs synchronously, so only the final delivery is faked.
	// -------------------------------------------------------------------------

	// =========================================================================
	// A watched SFO departure on 2026-07-01 with two listening users, plus a
	// user who isn't listening.
	private function watchWithListeners(): array {
		$flight = $this->makeFlight(['departure_date' => '2026-07-01']);
		$watch = Watch::create([
			'flight_id' => $flight->id,
			'subscription_id' => 'SUB123',
			'secret' => 'topsecret',
			'enabled' => true,
		]);

		$listeners = User::factory()->count(2)->create();
		foreach ($listeners as $user) {
			$user->listeners()->create(['watch_id' => $watch->id, 'travelers' => '1']);
		}

		return [$listeners, User::factory()->create()];
	}

	// =========================================================================
	private function postCallback(string $eventCode, ?string $scheduledOut) {
		return $this->postJson('/api/watch-callback?s=topsecret', [
			'alert_id' => 'SUB123',
			'event_code' => $eventCode,
			'summary' => "UA100 $eventCode",
			'flight' => [
				'fa_flight_id' => 'FA123',
				'ident' => 'UA100',
				'scheduled_out' => $scheduledOut,
			],
		]);
	}

	// =========================================================================
	public function test_matching_callback_notifies_every_listener_and_no_one_else(): void {
		Notification::fake();
		[$listeners, $bystander] = $this->watchWithListeners();

		$this->postCallback('departure', '2026-07-01T16:00:00Z')->assertStatus(200);

		foreach ($listeners as $user) {
			Notification::assertSentToTimes($user, Departure::class, 1);
		}
		Notification::assertNotSentTo($bystander, Departure::class);
	}

	// =========================================================================
	public function test_event_code_selects_the_notification_class(): void {
		Notification::fake();
		[$listeners] = $this->watchWithListeners();

		$this->postCallback('arrival', '2026-07-01T16:00:00Z')->assertStatus(200);

		Notification::assertSentTo($listeners[0], Arrival::class);
		Notification::assertNotSentTo($listeners[0], Departure::class);
	}

	// =========================================================================
	// The FA alert spans several days, so a daily flight's watch also gets
	// the previous day's callbacks (the QR701 case). They're stored but
	// mustn't notify anyone.
	public function test_previous_days_flight_is_stored_but_not_notified(): void {
		Notification::fake();
		$this->watchWithListeners();

		$this->postCallback('arrival', '2026-06-30T16:00:00Z')->assertStatus(200);

		$this->assertDatabaseHas('watch_callbacks', ['alert_id' => 'SUB123', 'event_code' => 'arrival']);
		Notification::assertNothingSent();
	}

	// =========================================================================
	public function test_next_days_flight_is_not_notified(): void {
		Notification::fake();
		$this->watchWithListeners();

		$this->postCallback('filed', '2026-07-02T16:00:00Z')->assertStatus(200);

		Notification::assertNothingSent();
	}

	// =========================================================================
	// 03:30Z on Jul 2 is 20:30 on Jul 1 in San Francisco -- the UTC date has
	// rolled over but it's still the watched flight.
	public function test_late_night_departure_matches_on_origin_local_date(): void {
		Notification::fake();
		[$listeners] = $this->watchWithListeners();

		$this->postCallback('departure', '2026-07-02T03:30:00Z')->assertStatus(200);

		Notification::assertSentTo($listeners[0], Departure::class);
	}

	// =========================================================================
	// 03:30Z on Jul 1 is 20:30 on Jun 30 in San Francisco -- same UTC date as
	// the flight, but the previous day's departure.
	public function test_same_utc_date_but_previous_local_date_is_not_notified(): void {
		Notification::fake();
		$this->watchWithListeners();

		$this->postCallback('departure', '2026-07-01T03:30:00Z')->assertStatus(200);

		Notification::assertNothingSent();
	}

	// =========================================================================
	public function test_callback_without_scheduled_out_is_stored_but_not_notified(): void {
		Notification::fake();
		$this->watchWithListeners();

		$this->postCallback('departure', null)->assertStatus(200);

		$this->assertSame(1, WatchCallback::count());
		Notification::assertNothingSent();
	}
}
