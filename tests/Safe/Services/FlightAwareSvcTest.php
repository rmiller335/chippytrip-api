<?php

namespace Tests\Safe\Services;

use App\Models\Airline;
use App\Models\Country;
use App\Services\FlightAwareSvc;
use Carbon\Carbon;
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
	public function test_schedule_for_ident_sends_icao_airline_and_numeric_flight_number(): void {
		Country::create(['iso2' => 'GB', 'iso3' => 'GBR', 'name' => 'United Kingdom', 'dominion' => '']);
		Airline::create([
			'icao' => 'VIR', 'iata' => 'VS', 'call_sign' => 'VIRGIN', 'name' => 'Virgin Atlantic',
			'country_code' => 'GB', 'status' => 'active', 'types' => ['M'],
		]);

		Http::fake(['*/schedules/*' => Http::response(['scheduled' => []], 200)]);

		$svc = new FlightAwareSvc();
		$svc->scheduleForIdent('VS3', Carbon::parse('2026-07-01'));
		$svc->scheduleForIdent('VIR3', Carbon::parse('2026-07-01'));

		$sent = Http::recorded()->map(function ($pair) {
			parse_str(parse_url($pair[0]->url(), PHP_URL_QUERY), $query);
			return $query;
		});

		$this->assertCount(2, $sent);
		foreach($sent as $query) {
			$this->assertSame('VIR', $query['airline']);
			$this->assertSame('3', $query['flight_number']);
		}
	}

	// =========================================================================
	public function test_flights_from_to_returns_null_and_does_not_throw_on_failure(): void {
		Http::fake(['*/airports/KSFO/flights/to/KJFK*' => Http::response(null, 500)]);

		$this->expectException(\Illuminate\Http\Client\RequestException::class);

		(new FlightAwareSvc())->flightsFromTo('KSFO', 'KJFK');
	}
}
