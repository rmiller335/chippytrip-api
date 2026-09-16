<?php

namespace Tests\Safe\Http\Controllers;

use App\Models\Airline;
use App\Models\Country;
use App\Models\User;
use Tests\Safe\TestCase;

// =============================================================================
class AirlineControllerTest extends TestCase {
	// =========================================================================
	protected function setUp(): void {
		parent::setUp();

		Country::create(['iso2' => 'US', 'iso3' => 'USA', 'name' => 'United States', 'dominion' => '']);
		Country::create(['iso2' => 'GB', 'iso3' => 'GBR', 'name' => 'United Kingdom', 'dominion' => '']);

		Airline::create([
			'icao' => 'UAL', 'iata' => 'UA', 'call_sign' => 'UNITED', 'name' => 'United Airlines',
			'country_code' => 'US', 'status' => 'active', 'types' => ['M'],
		]);

		Airline::create([
			'icao' => 'VIR', 'iata' => 'VS', 'call_sign' => 'VIRGIN', 'name' => 'Virgin Atlantic',
			'country_code' => 'GB', 'status' => 'active', 'types' => ['M'],
		]);
	}

	// =========================================================================
	public function test_search_matches_iata_code(): void {
		$user = User::factory()->create();

		$response = $this->actingAs($user, 'sanctum')
			->getJson('/api/airlines/search?q=UA');

		$response->assertStatus(200);
		$response->assertJsonCount(1);
		$response->assertJson([[
			'icao' => 'UAL', 'iata' => 'UA', 'name' => 'United Airlines',
		]]);
	}

	// =========================================================================
	public function test_search_matches_partial_name(): void {
		$user = User::factory()->create();

		$response = $this->actingAs($user, 'sanctum')
			->getJson('/api/airlines/search?q=virgin');

		$response->assertStatus(200);
		$response->assertJsonCount(1);
		$response->assertJsonFragment(['iata' => 'VS']);
	}

	// =========================================================================
	public function test_search_ranks_exact_code_match_first(): void {
		Airline::create([
			'icao' => 'UAX', 'iata' => 'UAX', 'call_sign' => 'UNITEDX', 'name' => 'United Express',
			'country_code' => 'US', 'status' => 'active', 'types' => ['M'],
		]);

		$user = User::factory()->create();

		$response = $this->actingAs($user, 'sanctum')
			->getJson('/api/airlines/search?q=UA');

		$response->assertStatus(200);
		$this->assertSame('UA', $response->json('0.iata'));
	}

	// =========================================================================
	public function test_search_requires_query_param(): void {
		$user = User::factory()->create();

		$response = $this->actingAs($user, 'sanctum')
			->getJson('/api/airlines/search');

		$response->assertStatus(422);
		$response->assertJsonValidationErrors(['q']);
	}

	// =========================================================================
	public function test_search_requires_authentication(): void {
		$response = $this->getJson('/api/airlines/search?q=UA');

		$response->assertStatus(401);
	}
}
