<?php

namespace Tests\Safe\Http\Controllers;

use App\Models\User;
use App\Models\Watch;
use App\Models\WatchCallback;
use Tests\Safe\TestCase;

// =============================================================================
class ListenerSyncControllerTest extends TestCase {
	// =========================================================================
	private function makeCallback(Watch $watch, string $faFlightId, string $eventCode, string $scheduledOut): WatchCallback {
		$wc = WatchCallback::fromApiPayload([
			'alert_id' => '1234567',
			'event_code' => $eventCode,
			'flight' => [
				'fa_flight_id' => $faFlightId,
				'ident' => 'UA100',
				'scheduled_out' => $scheduledOut,
			],
		]);
		$wc->watch_id = $watch->id;
		$wc->save();

		return $wc;
	}

	// =========================================================================
	// The FA alert spans several days, so the watch also collects callbacks
	// for the same ident on other days. Only this flight's should sync.
	public function test_sync_excludes_callbacks_for_other_days_flights(): void {
		$this->travelTo('2026-07-02 12:00:00');

		$flight = $this->makeFlight(['departure_date' => '2026-07-02']);
		$watch = Watch::create([
			'flight_id' => $flight->id,
			'subscription_id' => '1234567',
			'secret' => 'topsecret',
			'enabled' => true,
		]);
		$user = User::factory()->create();
		$user->listeners()->create(['watch_id' => $watch->id, 'travelers' => '1']);

		$yesterday = $this->makeCallback($watch, 'FA-JUL01', 'arrival', '2026-07-01T16:00:00Z');
		$today = $this->makeCallback($watch, 'FA-JUL02', 'departure', '2026-07-02T16:00:00Z');

		$response = $this->actingAs($user, 'sanctum')->getJson('/api/sync/listeners');

		$response->assertStatus(200);
		$ids = collect($response->json('flight_notifications'))->pluck('id');
		$this->assertTrue($ids->contains($today->id));
		$this->assertFalse($ids->contains($yesterday->id));
	}

	// =========================================================================
	private function watchFlight(User $user, string $departureDate): Watch {
		$flight = $this->makeFlight(['departure_date' => $departureDate]);
		$watch = Watch::create(['flight_id' => $flight->id]);
		$user->listeners()->create(['watch_id' => $watch->id, 'travelers' => '1']);

		return $watch;
	}

	// =========================================================================
	public function test_sync_only_includes_flights_within_past_days(): void {
		$this->travelTo('2026-07-20 12:00:00');
		config(['sync.past_days' => 14]);

		$user = User::factory()->create();
		$tooOld = $this->watchFlight($user, '2026-07-05');
		$cutoff = $this->watchFlight($user, '2026-07-06');
		$upcoming = $this->watchFlight($user, '2026-08-01');

		$response = $this->actingAs($user, 'sanctum')->getJson('/api/sync/listeners');

		$response->assertStatus(200);
		$watchIds = collect($response->json('watches'))->pluck('id');
		$this->assertEqualsCanonicalizing([$cutoff->id, $upcoming->id], $watchIds->all());
		$this->assertCount(2, $response->json('listeners'));
		$this->assertCount(2, $response->json('flights'));
	}
}
