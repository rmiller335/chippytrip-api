<?php

namespace Tests\Safe;

use App\Models\Airline;
use App\Models\Airport;
use App\Models\Country;
use App\Models\Flight;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

// =============================================================================
// Extends the framework's TestCase directly (not Tests\TestCase) because that
// class uses DatabaseTransactions, which conflicts with RefreshDatabase below:
// both would try to open a transaction and PDO throws "already an active
// transaction".
abstract class TestCase extends BaseTestCase {
	use RefreshDatabase;

	// =========================================================================
	protected function setUp(): void {
		parent::setUp();

		// Fail loudly on any real outbound request instead of hitting a live API.
		// Tests register their own Http::fake([...]) for the URLs they expect to hit.
		Http::preventStrayRequests();
	}

	// =========================================================================
	/**
	 * The countries migration runs `countries:update`, which scrapes a live
	 * Wikipedia page. Fake it to an empty response before migrations run so
	 * tests never make a real network call or get seeded with real data.
	 */
	protected function beforeRefreshingDatabase(): void {
		Http::fake([
			'*en.wikipedia.org/*' => Http::response('<html></html>', 200),
		]);
	}

	// =========================================================================
	/**
	 * Create a Flight with the Country/Airline/Airport rows its foreign
	 * keys require, so callers can focus on the fields they care about.
	 */
	protected function makeFlight(array $overrides = []): Flight {
		Country::firstOrCreate(
			['iso2' => 'US'],
			['iso3' => 'USA', 'name' => 'United States', 'dominion' => '']
		);

		Airline::firstOrCreate(
			['icao' => 'UAL'],
			[
				'iata' => 'UA', 'call_sign' => 'UNITED', 'name' => 'United Airlines',
				'country_code' => 'US', 'status' => 'active', 'types' => ['M'],
			]
		);

		Airport::firstOrCreate(
			['icao' => 'KSFO'],
			[
				'iata' => 'SFO', 'name' => 'San Francisco Intl', 'city' => 'San Francisco', 'state' => 'CA',
				'longitude' => -122.379, 'latitude' => 37.619, 'timezone' => 'America/Los_Angeles',
				'country_code' => 'US',
			]
		);

		Airport::firstOrCreate(
			['icao' => 'KJFK'],
			[
				'iata' => 'JFK', 'name' => 'John F Kennedy Intl', 'city' => 'New York', 'state' => 'NY',
				'longitude' => -73.7781, 'latitude' => 40.6413, 'timezone' => 'America/New_York',
				'country_code' => 'US',
			]
		);

		return Flight::create(array_merge([
			'airline_icao' => 'UAL',
			'flight_no' => '100',
			'origin_icao' => 'KSFO',
			'destination_icao' => 'KJFK',
			'flight' => 'UA100',
			'departure_date' => Carbon::now()->toDateString(),
		], $overrides));
	}
}
