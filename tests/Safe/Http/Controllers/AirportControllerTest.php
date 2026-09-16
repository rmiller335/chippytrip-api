<?php

namespace Tests\Safe\Http\Controllers;

use App\Models\Airport;
use App\Models\Country;
use App\Models\User;
use Tests\Safe\TestCase;

// =============================================================================
class AirportControllerTest extends TestCase {
	// =========================================================================
	protected function setUp(): void {
		parent::setUp();

		Country::create(['iso2' => 'US', 'iso3' => 'USA', 'name' => 'United States', 'dominion' => '']);
		Country::create(['iso2' => 'GB', 'iso3' => 'GBR', 'name' => 'United Kingdom', 'dominion' => '']);

		Airport::create([
			'icao' => 'KSFO', 'iata' => 'SFO', 'name' => 'San Francisco Intl', 'city' => 'San Francisco',
			'state' => 'CA', 'longitude' => -122.379, 'latitude' => 37.619, 'timezone' => 'America/Los_Angeles',
			'country_code' => 'US',
		]);

		Airport::create([
			'icao' => 'KJFK', 'iata' => 'JFK', 'name' => 'John F Kennedy Intl', 'city' => 'New York',
			'state' => 'NY', 'longitude' => -73.7781, 'latitude' => 40.6413, 'timezone' => 'America/New_York',
			'country_code' => 'US',
		]);

		Airport::create([
			'icao' => 'EGLL', 'iata' => 'LHR', 'name' => 'London Heathrow', 'city' => 'London',
			'state' => '', 'longitude' => -0.4543, 'latitude' => 51.4700, 'timezone' => 'Europe/London',
			'country_code' => 'GB',
		]);
	}

	// =========================================================================
	public function test_search_matches_iata_code(): void {
		$user = User::factory()->create();

		$response = $this->actingAs($user, 'sanctum')
			->getJson('/api/airports/search?q=SFO');

		$response->assertStatus(200);
		$response->assertJsonCount(1);
		$response->assertJson([[
			'icao' => 'KSFO', 'iata' => 'SFO', 'name' => 'San Francisco Intl', 'city' => 'San Francisco',
		]]);
	}

	// =========================================================================
	public function test_search_matches_partial_city(): void {
		$user = User::factory()->create();

		$response = $this->actingAs($user, 'sanctum')
			->getJson('/api/airports/search?q=fran');

		$response->assertStatus(200);
		$response->assertJsonCount(1);
		$response->assertJsonFragment(['iata' => 'SFO']);
	}

	// =========================================================================
	public function test_search_matches_partial_name(): void {
		$user = User::factory()->create();

		$response = $this->actingAs($user, 'sanctum')
			->getJson('/api/airports/search?q=heathrow');

		$response->assertStatus(200);
		$response->assertJsonCount(1);
		$response->assertJsonFragment(['iata' => 'LHR']);
	}

	// =========================================================================
	public function test_search_ranks_exact_code_match_first(): void {
		Airport::create([
			'icao' => 'KLHX', 'iata' => 'LHX', 'name' => 'Something LHR-ish', 'city' => 'Lehighton',
			'state' => 'PA', 'longitude' => 0, 'latitude' => 0, 'timezone' => 'America/New_York',
			'country_code' => 'US',
		]);

		$user = User::factory()->create();

		$response = $this->actingAs($user, 'sanctum')
			->getJson('/api/airports/search?q=LHR');

		$response->assertStatus(200);
		$this->assertSame('LHR', $response->json('0.iata'));
	}

	// =========================================================================
	public function test_search_requires_query_param(): void {
		$user = User::factory()->create();

		$response = $this->actingAs($user, 'sanctum')
			->getJson('/api/airports/search');

		$response->assertStatus(422);
		$response->assertJsonValidationErrors(['q']);
	}

	// =========================================================================
	public function test_search_requires_authentication(): void {
		$response = $this->getJson('/api/airports/search?q=SFO');

		$response->assertStatus(401);
	}
}
