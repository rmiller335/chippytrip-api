<?php

namespace Tests\Safe\Http\Controllers;

use App\Jobs\NotificationsIndex;
use App\Jobs\SendNotification;
use App\Models\User;
use App\Models\Watch;
use Carbon\Carbon;
use Illuminate\Support\Facades\Bus;
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
		$this->assertDatabaseHas('watch_callbacks', ['alert_id' => 'SUB123']);

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
}
