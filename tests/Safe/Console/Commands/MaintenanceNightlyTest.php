<?php

namespace Tests\Safe\Console\Commands;

use App\Jobs\DisableWatch;
use App\Jobs\EnableWatch;
use App\Models\User;
use App\Models\Watch;
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
			'subscription_id' => 'SUB-B',
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
			(object) ['id' => 'SUB-ORPHAN'],
		]);
		$fa->shouldReceive('watchDelete')->once()->with('SUB-ORPHAN');
		$this->app->instance(FlightAwareSvc::class, $fa);

		$this->artisan('maintenance:nightly')->assertExitCode(0);
	}

	// =========================================================================
	public function test_does_not_prune_remote_alerts_that_have_a_matching_local_watch(): void {
		Bus::fake();

		$flight = $this->makeFlight(['flight' => 'UA300']);
		Watch::create([
			'flight_id' => $flight->id,
			'subscription_id' => 'SUB-KNOWN',
			'enabled' => false,
		]);

		// FlightAware's alert list is eventually consistent, so a newly created
		// or deleted alert may not be reflected in watchList() right away.
		// pruneAlerts() only trusts our own database as the source of truth for
		// what should exist -> it must never delete an alert that still has a
		// matching Watch record, regardless of how stale FlightAware's view is.
		$fa = \Mockery::mock(FlightAwareSvc::class);
		$fa->shouldReceive('watchList')->once()->andReturn([
			(object) ['id' => 'SUB-KNOWN'],
		]);
		$fa->shouldNotReceive('watchDelete');
		$this->app->instance(FlightAwareSvc::class, $fa);

		$this->artisan('maintenance:nightly')->assertExitCode(0);
	}
}
