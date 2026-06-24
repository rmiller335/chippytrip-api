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
	public function test_enables_watches_entering_their_alert_window_and_disables_ones_leaving_it(): void {
		Bus::fake();

		$user = User::factory()->create();

		// Flight A departs today: inside its alert window, currently disabled
		// -> enableNew() should pick it up.
		$flightA = $this->makeFlight([
			'flight' => 'UA100',
			'departure_date' => Carbon::now()->toDateString(),
		]);
		$watchA = Watch::create(['flight_id' => $flightA->id, 'enabled' => false]);
		$user->listeners()->create(['watch_id' => $watchA->id, 'travelers' => '1']);

		// Flight B departs in 30 days: outside its alert window, but FA
		// reports it as an active alert -> syncAlerts() re-enables it, then
		// disableOld() should pick it back up for disabling.
		$flightB = $this->makeFlight([
			'flight' => 'UA200',
			'origin_icao' => 'KJFK',
			'destination_icao' => 'KSFO',
			'departure_date' => Carbon::now()->addDays(30)->toDateString(),
		]);
		$watchB = Watch::create([
			'flight_id' => $flightB->id,
			'subscription_id' => 'SUB-B',
			'enabled' => false,
		]);
		$user->listeners()->create(['watch_id' => $watchB->id, 'travelers' => '1']);

		$fa = \Mockery::mock(FlightAwareSvc::class);
		$fa->shouldReceive('watchList')->once()->andReturn([
			(object) ['id' => 'SUB-B'],
		]);
		$this->app->instance(FlightAwareSvc::class, $fa);

		$this->artisan('maintenance:nightly')->assertExitCode(0);

		Bus::assertDispatched(EnableWatch::class, fn ($job) => $job->watch->is($watchA));
		Bus::assertDispatched(DisableWatch::class, fn ($job) => $job->watch->is($watchB));
	}
}
