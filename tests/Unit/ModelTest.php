<?php

namespace Tests\Unit;

use App\Models\Airline;
use App\Models\Airport;
use App\Models\Flight;
use App\Models\Country;
use Tests\TestCase;

// =============================================================================
class ModelTest extends TestCase {
	private bool $verbose = false;

	// =========================================================================
    public function test_airline(): void {
		$airline = Airline::first();
		$country = $airline->country;
		$this->assertNotNull($country);

		if($this->verbose) {
			fwrite(STDERR, "========================= Airline Country ...\n");
			fwrite(STDERR, json_encode($country, JSON_PRETTY_PRINT) . "\n");
		}
    }

	// =========================================================================
    public function test_airport(): void {
		$airport = Airport::first();
		$country = $airport->country;
		$this->assertNotNull($country);

		if($this->verbose) {
			fwrite(STDERR, "========================= Airport Country ...\n");
			fwrite(STDERR, json_encode($country, JSON_PRETTY_PRINT) . "\n");
		}
    }

	// =========================================================================
    public function test_country(): void {
		$country = Country::where('iso2', 'AT')->first();

		$airlines = $country->airlines;
		$this->assertGreaterThan(0, $airlines->count());

		if($this->verbose) {
			fwrite(STDERR, "========================= Austria Airlines...\n");
			fwrite(STDERR, json_encode($airlines->take(2), JSON_PRETTY_PRINT) . "\n");
		}
		
		$airports = $country->airports;
		$this->assertGreaterThan(0, $airports->count());

		if($this->verbose) {
			fwrite(STDERR, "========================= Austria Airports...\n");
			fwrite(STDERR, json_encode($airports->take(2), JSON_PRETTY_PRINT) . "\n");
		}
	}

	// =========================================================================
	public function test_flight(): void {
		$flight = Flight::first();

		$airline = $flight->airline;
		$this->assertNotNull($airline);

		if($this->verbose) {
			fwrite(STDERR, "========================= Flight Airline...\n");
			fwrite(STDERR, json_encode($airline, JSON_PRETTY_PRINT) . "\n");
		}

		$origin = $flight->origin;
		$this->assertNotNull($origin);

		if($this->verbose) {
			fwrite(STDERR, "========================= Flight Origin...\n");
			fwrite(STDERR, json_encode($origin, JSON_PRETTY_PRINT) . "\n");
		}

		$destination = $flight->destination;
		$this->assertNotNull($destination);

		if($this->verbose) {
			fwrite(STDERR, "========================= Flight Destination...\n");
			fwrite(STDERR, json_encode($destination, JSON_PRETTY_PRINT) . "\n");
		}
	}
}
