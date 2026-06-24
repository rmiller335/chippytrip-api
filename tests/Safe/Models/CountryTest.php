<?php

namespace Tests\Safe\Models;

use App\Models\Airline;
use App\Models\Airport;
use App\Models\Country;
use Tests\Safe\TestCase;

// =============================================================================
class CountryTest extends TestCase {
	// =========================================================================
	public function test_equal(): void {
		$a = new Country(['iso2' => 'US', 'iso3' => 'USA', 'name' => 'United States', 'dominion' => '']);
		$b = new Country(['iso2' => 'US', 'iso3' => 'USA', 'name' => 'United States', 'dominion' => '']);
		$c = new Country(['iso2' => 'GB', 'iso3' => 'GBR', 'name' => 'United Kingdom', 'dominion' => '']);

		$this->assertTrue($a->equal($b));
		$this->assertFalse($a->equal($c));
	}

	// =========================================================================
	public function test_airlines_and_airports_relations(): void {
		$country = Country::create(['iso2' => 'US', 'iso3' => 'USA', 'name' => 'United States', 'dominion' => '']);

		Airline::create([
			'icao' => 'UAL', 'iata' => 'UA', 'call_sign' => 'UNITED', 'name' => 'United Airlines',
			'country_code' => 'US', 'status' => 'active', 'types' => ['M'],
		]);

		Airport::create([
			'icao' => 'KSFO', 'iata' => 'SFO', 'name' => 'San Francisco Intl', 'city' => 'San Francisco',
			'state' => 'CA', 'longitude' => -122.379, 'latitude' => 37.619, 'timezone' => 'America/Los_Angeles',
			'country_code' => 'US',
		]);

		$this->assertCount(1, $country->airlines);
		$this->assertCount(1, $country->airports);
	}
}
