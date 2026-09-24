<?php

namespace Tests\Safe\Console\Commands;

use App\Jobs\DisableWatch;
use App\Jobs\EnableWatch;
use App\Models\EmailRelatedRecord;
use App\Models\Flight;
use App\Models\InboundEmail;
use App\Models\User;
use App\Models\Watch;
use App\Models\WatchCallback;
use App\Services\FlightAwareSvc;
use Carbon\Carbon;
use Illuminate\Support\Facades\Bus;
use Tests\Safe\TestCase;

// =============================================================================
class MaintenanceNightlyTest extends TestCase {
	// =========================================================================
	public function test_enables_watches_entering_their_alert_window(): void {
		Bus::fake();

		$user = User::factory()->create();

		// Flight departs today: inside its alert window, currently disabled
		// -> enableNew() should pick it up.
		$flight = $this->makeFlight([
			'flight' => 'UA100',
			'departure_date' => Carbon::now()->toDateString(),
		]);
		$watch = Watch::create(['flight_id' => $flight->id, 'enabled' => false]);
		$user->listeners()->create(['watch_id' => $watch->id, 'travelers' => '1']);

		$fa = \Mockery::mock(FlightAwareSvc::class);
		$fa->shouldReceive('watchList')->once()->andReturn([]);
		$this->app->instance(FlightAwareSvc::class, $fa);

		$this->artisan('maintenance:nightly')->assertExitCode(0);

		Bus::assertDispatched(EnableWatch::class, fn ($job) => $job->watch->is($watch));
	}

	// =========================================================================
	public function test_disables_watches_leaving_their_alert_window(): void {
		Bus::fake();

		$user = User::factory()->create();

		// Flight departs in 30 days: outside its alert window, but currently
		// enabled -> disableOld() should pick it up. This is driven purely by
		// local watch state, not by what FlightAware's alert list reports, since
		// that list lags behind our own database (see pruneAlerts tests below).
		$flight = $this->makeFlight([
			'flight' => 'UA200',
			'origin_icao' => 'KJFK',
			'destination_icao' => 'KSFO',
			'departure_date' => Carbon::now()->addDays(30)->toDateString(),
		]);
		$watch = Watch::create([
			'flight_id' => $flight->id,
			'subscription_id' => '2000001',
			'enabled' => true,
		]);
		$user->listeners()->create(['watch_id' => $watch->id, 'travelers' => '1']);

		$fa = \Mockery::mock(FlightAwareSvc::class);
		$fa->shouldReceive('watchList')->once()->andReturn([]);
		$this->app->instance(FlightAwareSvc::class, $fa);

		$this->artisan('maintenance:nightly')->assertExitCode(0);

		Bus::assertDispatched(DisableWatch::class, fn ($job) => $job->watch->is($watch));
	}

	// =========================================================================
	public function test_prunes_remote_alerts_with_no_matching_local_watch(): void {
		Bus::fake();

		// FlightAware reports an alert we have no record of locally (e.g. its
		// matching Watch was already deleted here) -> pruneAlerts() should
		// delete it remotely.
		$fa = \Mockery::mock(FlightAwareSvc::class);
		$fa->shouldReceive('watchList')->once()->andReturn([
			(object) ['id' => '2000002'],
		]);
		$fa->shouldReceive('watchDelete')->once()->with('2000002');
		$this->app->instance(FlightAwareSvc::class, $fa);

		$this->artisan('maintenance:nightly')->assertExitCode(0);
	}

	// =========================================================================
	public function test_does_not_prune_remote_alerts_that_have_a_matching_local_watch(): void {
		Bus::fake();

		$flight = $this->makeFlight(['flight' => 'UA300']);
		$watch = Watch::create([
			'flight_id' => $flight->id,
			'subscription_id' => '2000003',
			'enabled' => false,
		]);
		// A listener, so pruneUnwatched() keeps the watch.
		User::factory()->create()->listeners()->create(['watch_id' => $watch->id, 'travelers' => '1']);

		// FlightAware's alert list is eventually consistent, so a newly created
		// or deleted alert may not be reflected in watchList() right away.
		// pruneAlerts() only trusts our own database as the source of truth for
		// what should exist -> it must never delete an alert that still has a
		// matching Watch record, regardless of how stale FlightAware's view is.
		$fa = \Mockery::mock(FlightAwareSvc::class);
		$fa->shouldReceive('watchList')->once()->andReturn([
			(object) ['id' => '2000003'],
		]);
		$fa->shouldNotReceive('watchDelete');
		$this->app->instance(FlightAwareSvc::class, $fa);

		$this->artisan('maintenance:nightly')->assertExitCode(0);
	}

	// =========================================================================
	private function makeCallback(?Watch $watch): WatchCallback {
		$wc = WatchCallback::fromApiPayload([
			'alert_id' => $watch?->subscription_id ?? '2000004',
			'event_code' => 'departure',
			'flight' => ['fa_flight_id' => 'FA123', 'ident' => 'UA100'],
		]);
		$wc->watch_id = $watch?->id;
		$wc->save();

		return $wc;
	}

	// =========================================================================
	private function mockFlightAware(array $alertIds, array $expectDeleted): void {
		$fa = \Mockery::mock(FlightAwareSvc::class);
		$fa->shouldReceive('watchList')->once()
			->andReturn(array_map(fn ($id) => (object) ['id' => $id], $alertIds));

		if (empty($expectDeleted)) {
			$fa->shouldNotReceive('watchDelete');
		}
		foreach ($expectDeleted as $id) {
			$fa->shouldReceive('watchDelete')->once()->with($id);
		}

		$this->app->instance(FlightAwareSvc::class, $fa);
	}

	// =========================================================================
	// Once the last listener is removed (DELETE /flights/{flight}), the next
	// run deletes the watch, its flight, callbacks, email links and alert.
	public function test_prunes_unlistened_watch_with_its_flight_callbacks_and_alert(): void {
		Bus::fake();

		$flight = $this->makeFlight();
		$watch = Watch::create([
			'flight_id' => $flight->id,
			'subscription_id' => '2000005',
			'enabled' => true,
		]);
		$callback = $this->makeCallback($watch);

		$user = User::factory()->create();
		$email = InboundEmail::create([
			'user_id' => $user->id,
			'message_id' => 'msg-1',
			'from_address' => $user->email,
			'subject' => 'Your booking',
			'status' => 'processed',
		]);
		EmailRelatedRecord::create([
			'inbound_email_id' => $email->id,
			'record_type' => Flight::class,
			'record_id' => $flight->id,
		]);

		$this->mockFlightAware(['2000005'], ['2000005']);

		$this->artisan('maintenance:nightly')->assertExitCode(0);

		$this->assertModelMissing($watch);
		$this->assertModelMissing($flight);
		$this->assertModelMissing($callback);
		$this->assertDatabaseCount('email_related_records', 0);
		$this->assertModelExists($email);
	}

	// =========================================================================
	public function test_keeps_listened_watch_with_its_flight_and_callbacks(): void {
		Bus::fake();

		$flight = $this->makeFlight();
		$watch = Watch::create([
			'flight_id' => $flight->id,
			'subscription_id' => '2000006',
			'enabled' => true,
		]);
		User::factory()->create()->listeners()->create(['watch_id' => $watch->id, 'travelers' => '1']);
		$callback = $this->makeCallback($watch);

		$this->mockFlightAware(['2000006'], []);

		$this->artisan('maintenance:nightly')->assertExitCode(0);

		$this->assertModelExists($watch);
		$this->assertModelExists($flight);
		$this->assertModelExists($callback);
	}

	// =========================================================================
	// Callbacks from before watch_id existed whose watch couldn't be matched.
	public function test_deletes_callbacks_not_linked_to_a_watch(): void {
		Bus::fake();

		$callback = $this->makeCallback(null);

		$this->mockFlightAware([], []);

		$this->artisan('maintenance:nightly')->assertExitCode(0);

		$this->assertModelMissing($callback);
	}
}
