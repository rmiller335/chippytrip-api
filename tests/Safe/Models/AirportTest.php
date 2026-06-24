<?php

namespace Tests\Safe\Models;

use App\Models\Airport;
use App\Models\Country;
use Illuminate\Support\Facades\Http;
use Tests\Safe\TestCase;

// =============================================================================
class AirportTest extends TestCase {
	// =========================================================================
	protected function setUp(): void {
		parent::setUp();

		Country::create(['iso2' => 'US', 'iso3' => 'USA', 'name' => 'United States', 'dominion' => '']);
	}

	// =========================================================================
	public function test_icao_for_iata(): void {
		Airport::create([
			'icao' => 'KSFO', 'iata' => 'SFO', 'name' => 'San Francisco Intl', 'city' => 'San Francisco',
			'state' => 'CA', 'longitude' => -122.379, 'latitude' => 37.619, 'timezone' => 'America/Los_Angeles',
			'country_code' => 'US',
		]);

		$this->assertSame('KSFO', Airport::icaoForIata('SFO'));
	}

	// =========================================================================
	public function test_country_relation(): void {
		$airport = Airport::create([
			'icao' => 'KSFO', 'iata' => 'SFO', 'name' => 'San Francisco Intl', 'city' => 'San Francisco',
			'state' => 'CA', 'longitude' => -122.379, 'latitude' => 37.619, 'timezone' => 'America/Los_Angeles',
			'country_code' => 'US',
		]);

		$this->assertSame('United States', $airport->country->name);
	}

	// =========================================================================
	public function test_find_or_fetch_returns_existing_airport_without_calling_flightaware(): void {
		Airport::create([
			'icao' => 'KSFO', 'iata' => 'SFO', 'name' => 'San Francisco Intl', 'city' => 'San Francisco',
			'state' => 'CA', 'longitude' => -122.379, 'latitude' => 37.619, 'timezone' => 'America/Los_Angeles',
			'country_code' => 'US',
		]);

		$airport = Airport::findOrFetch('KSFO');

		$this->assertSame('San Francisco Intl', $airport->name);
	}

	// =========================================================================
	public function test_find_or_fetch_creates_airport_from_flightaware_when_missing(): void {
		Http::fake([
			'*/airports/KJFK' => Http::response([
				'code_icao' => 'KJFK',
				'code_iata' => 'JFK',
				'name' => 'John F Kennedy Intl',
				'elevation' => 13,
				'city' => 'New York',
				'state' => 'NY',
				'longitude' => -73.7781,
				'latitude' => 40.6413,
				'timezone' => 'America/New_York',
				'country_code' => 'US',
				'wiki_url' => 'https://en.wikipedia.org/wiki/JFK',
				'airport_flights_url' => '/airports/KJFK/flights',
				'alternatives' => ['KJFK'],
			], 200),
		]);

		$airport = Airport::findOrFetch('KJFK');

		$this->assertSame('John F Kennedy Intl', $airport->name);
		$this->assertSame(13, $airport->elevation);
		$this->assertSame(['KJFK'], $airport->alternatives);
		$this->assertDatabaseHas('airports', ['icao' => 'KJFK']);
	}
}
