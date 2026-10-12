<?php

namespace Tests\Safe\Jobs;

use App\Jobs\EnableWatch;
use App\Models\Watch;
use App\Services\FlightAwareSvc;
use Illuminate\Support\Facades\Http;
use Tests\Safe\TestCase;

// =============================================================================
class EnableWatchTest extends TestCase {
	// =========================================================================
	public function test_handle_enables_watch_on_successful_subscription(): void {
		$flight = $this->makeFlight();
		$watch = Watch::create(['flight_id' => $flight->id]);

		Http::fake([
			'*/alerts' => Http::response(null, 201, ['Location' => '/alerts/1234567']),
		]);

		(new EnableWatch($watch))->handle(app(FlightAwareSvc::class));

		$watch->refresh();
		$this->assertTrue($watch->enabled);
		$this->assertSame('1234567', $watch->subscription_id);
		$this->assertNotNull($watch->secret);
	}

	// =========================================================================
	public function test_handle_throws_when_flightaware_rejects_the_subscription(): void {
		$flight = $this->makeFlight();
		$watch = Watch::create(['flight_id' => $flight->id]);

		Http::fake([
			'*/alerts' => Http::response(null, 500),
		]);

		// FlightAwareSvc::watchCreate() aborts(500) on failure rather than
		// returning falsy, so EnableWatch's own Log::error()/fail() branch
		// is currently unreachable -- the exception propagates instead.
		$this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

		(new EnableWatch($watch))->handle(app(FlightAwareSvc::class));
	}

	// =========================================================================
	// start/end are departure dates in the origin's time zone (end inclusive),
	// taken from the flight, not from when the alert is created.
	public function test_alert_window_is_the_departure_date_plus_buffer(): void {
		$flight = $this->makeFlight(['departure_date' => now()->addDays(5)->toDateString()]);
		$watch = Watch::create(['flight_id' => $flight->id]);
		$date = now()->addDays(5);

		Http::fake([
			'*/alerts' => Http::response(null, 201, ['Location' => '/alerts/1234567']),
		]);

		(new EnableWatch($watch))->handle(app(FlightAwareSvc::class));

		Http::assertSent(fn ($request) => str_ends_with($request->url(), '/alerts')
			&& $request['start'] === $date->copy()->subDay()->toDateString()
			&& $request['end'] === $date->copy()->addDay()->toDateString());
	}

	// =========================================================================
	public function test_alert_window_without_buffer_is_just_the_departure_date(): void {
		config(['flightaware.alert_buffer_days' => 0]);
		$date = now('America/Los_Angeles')->addDays(4)->toDateString();
		$flight = $this->makeFlight(['departure_date' => $date]);
		$watch = Watch::create(['flight_id' => $flight->id]);

		Http::fake([
			'*/alerts' => Http::response(null, 201, ['Location' => '/alerts/1234567']),
		]);

		(new EnableWatch($watch))->handle(app(FlightAwareSvc::class));

		Http::assertSent(fn ($request) => str_ends_with($request->url(), '/alerts')
			&& $request['start'] === $date && $request['end'] === $date);
	}

	// =========================================================================
	// AeroAPI alerts on a codeshare's marketing ident never fire.
	public function test_alert_uses_the_operating_ident(): void {
		$flight = $this->makeFlight(['operating_ident' => 'SKW5100']);
		$watch = Watch::create(['flight_id' => $flight->id]);

		Http::fake([
			'*/alerts' => Http::response(null, 201, ['Location' => '/alerts/1234567']),
		]);

		(new EnableWatch($watch))->handle(app(FlightAwareSvc::class));

		Http::assertSent(fn ($request) => str_ends_with($request->url(), '/alerts')
			&& $request['ident'] === 'SKW5100');
	}

	// =========================================================================
	public function test_operating_ident_is_looked_up_when_missing(): void {
		$flight = $this->makeFlight(['departure_date' => now()->addDays(3)->toDateString()]);
		$watch = Watch::create(['flight_id' => $flight->id]);

		Http::fake([
			'*/schedules/*' => Http::response(['scheduled' => [[
				'ident_iata' => 'UA100',
				'ident_icao' => 'UAL100',
				'actual_ident_icao' => 'SKW5100',
				'origin' => 'KSFO',
				'scheduled_out' => $flight->departure_date->toDateString() . 'T16:00:00Z',
			]]], 200),
			'*/alerts' => Http::response(null, 201, ['Location' => '/alerts/1234567']),
		]);

		(new EnableWatch($watch))->handle(app(FlightAwareSvc::class));

		$this->assertSame('SKW5100', $flight->refresh()->operating_ident);
		Http::assertSent(fn ($request) => str_ends_with($request->url(), '/alerts')
			&& $request['ident'] === 'SKW5100');
	}

	// =========================================================================
	// AeroAPI rejects a start date in the past: a flight departing today
	// starts its alert today (at the origin), not the day before.
	public function test_alert_for_todays_flight_does_not_start_in_the_past(): void {
		$today = now('America/Los_Angeles')->toDateString();
		$flight = $this->makeFlight(['departure_date' => $today, 'operating_ident' => 'UAL100']);
		$watch = Watch::create(['flight_id' => $flight->id]);

		Http::fake([
			'*/alerts' => Http::response(null, 201, ['Location' => '/alerts/1234567']),
		]);

		(new EnableWatch($watch))->handle(app(FlightAwareSvc::class));

		Http::assertSent(fn ($request) => str_ends_with($request->url(), '/alerts')
			&& $request['start'] === $today
			&& $request['end'] === \Carbon\Carbon::parse($today)->addDay()->toDateString());
	}
}
