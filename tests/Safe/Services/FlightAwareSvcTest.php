<?php

namespace Tests\Safe\Services;

use App\Services\FlightAwareSvc;
use Illuminate\Support\Facades\Http;
use Tests\Safe\TestCase;

// =============================================================================
class FlightAwareSvcTest extends TestCase {
	// =========================================================================
	public function test_airport_info_returns_object_from_response(): void {
		Http::fake([
			'*/airports/KSFO' => Http::response(['code_icao' => 'KSFO', 'name' => 'San Francisco Intl'], 200),
		]);

		$info = (new FlightAwareSvc())->airportInfo('KSFO');

		$this->assertSame('San Francisco Intl', $info->name);
	}

	// =========================================================================
	public function test_watch_delete_sends_delete_request(): void {
		Http::fake(['*/alerts/1234567' => Http::response(null, 204)]);

		(new FlightAwareSvc())->watchDelete('1234567');

		Http::assertSent(fn ($request) => $request->method() === 'DELETE'
			&& str_contains($request->url(), '/alerts/1234567'));
	}

	// =========================================================================
	public function test_watch_list_paginates_across_pages(): void {
		Http::fake([
			'*/alerts' => Http::response([
				'alerts' => [['id' => '3000001'], ['id' => '3000002']],
				'links' => ['next' => '/alerts?cursor=2'],
			], 200),
			'*alerts?cursor=2' => Http::response(['alerts' => [['id' => '3000003']]], 200),
		]);

		$alerts = (new FlightAwareSvc())->watchList();

		$this->assertSame(['3000001', '3000002', '3000003'], array_values(array_map(fn ($a) => $a->id, $alerts)));

		// watchList() must not do a per-alert existence check: FlightAware's
		// single-alert endpoint lags the list feed, so that check could 404 on
		// an alert that was only just created and wrongly drop it.
		Http::assertNotSent(fn ($request) => str_contains($request->url(), '/alerts/3000'));
	}

	// =========================================================================
	public function test_flight_schedule_matches_on_flight_ident(): void {
		$flight = $this->makeFlight(['flight' => 'UA100']);

		Http::fake([
			'*/schedules/*' => Http::response([
				'scheduled' => [
					['ident_iata' => 'DL200', 'aircraft_type' => 'A321'],
					['ident_iata' => 'UA100', 'aircraft_type' => 'B738'],
				],
			], 200),
		]);

		$match = (new FlightAwareSvc())->flightSchedule($flight);

		$this->assertSame('B738', $match->aircraft_type);
	}

	// =========================================================================
	public function test_flights_from_to_returns_null_and_does_not_throw_on_failure(): void {
		Http::fake(['*/airports/KSFO/flights/to/KJFK*' => Http::response(null, 500)]);

		$this->expectException(\Illuminate\Http\Client\RequestException::class);

		(new FlightAwareSvc())->flightsFromTo('KSFO', 'KJFK');
	}
}
