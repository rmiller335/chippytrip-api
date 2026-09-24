<?php

namespace Tests\Safe\Http\Controllers;

use App\Models\Airline;
use App\Models\Airport;
use App\Models\Country;
use App\Models\Flight;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\Safe\TestCase;

// =============================================================================
// Covers FlightSearchController::watch() and search() — checkFlight() is
// covered by tests/Feature/FlightSearchControllerTest.php.
class FlightSearchControllerTest extends TestCase {
	// =========================================================================
	protected function setUp(): void {
		parent::setUp();

		Country::create(['iso2' => 'US', 'iso3' => 'USA', 'name' => 'United States', 'dominion' => '']);
		Country::create(['iso2' => 'GB', 'iso3' => 'GBR', 'name' => 'United Kingdom', 'dominion' => '']);

		Airline::create([
			'icao' => 'VIR', 'iata' => 'VS', 'call_sign' => 'VIRGIN', 'name' => 'Virgin Atlantic',
			'country_code' => 'GB', 'status' => 'active', 'types' => ['M'],
		]);

		Airport::create([
			'icao' => 'EGLL', 'iata' => 'LHR', 'name' => 'London Heathrow', 'city' => 'London',
			'state' => '', 'longitude' => -0.4543, 'latitude' => 51.4700, 'timezone' => 'Europe/London',
			'country_code' => 'GB',
		]);

		Airport::create([
			'icao' => 'KJFK', 'iata' => 'JFK', 'name' => 'John F Kennedy Intl', 'city' => 'New York',
			'state' => 'NY', 'longitude' => -73.7781, 'latitude' => 40.6413, 'timezone' => 'America/New_York',
			'country_code' => 'US',
		]);
	}

	// =========================================================================
	private function fakeSchedule(): void {
		Http::fake([
			'*/schedules/*' => Http::response([
				'scheduled' => [[
					'ident_iata' => 'VS3',
					'scheduled_out' => '2026-07-01T14:00:00Z',
					'scheduled_in' => '2026-07-01T22:00:00Z',
					'aircraft_type' => 'A350',
					'meal_service' => 'dinner',
					'seats_cabin_first' => 0,
					'seats_cabin_business' => 31,
					'seats_cabin_coach' => 227,
				]],
			], 200),
		]);
	}

	// =========================================================================
	public function test_watch_creates_flight_watch_and_listener_when_none_exist(): void {
		$user = User::factory()->create();
		$this->fakeSchedule();

		$response = $this->actingAs($user, 'sanctum')
			->postJson('/api/watches', [
				'flight_number' => 'VS3',
				'origin' => 'EGLL',
				'destination' => 'KJFK',
				'date' => Carbon::now()->addDays(10)->toDateString(),
			]);

		$response->assertStatus(200);
		$response->assertJsonPath('flight.flight', 'VS3');
		$response->assertJsonPath('listener.travelers', $user->name);

		$this->assertDatabaseHas('flights', ['flight' => 'VS3', 'equipment' => 'A350']);
		$this->assertDatabaseHas('watches', ['flight_id' => Flight::firstWhere('flight', 'VS3')->id]);
		$this->assertDatabaseHas('listeners', ['user_id' => $user->id]);
	}

	// =========================================================================
	public function test_watch_reuses_existing_flight_and_watch(): void {
		$user = User::factory()->create();
		$date = Carbon::now()->addDays(10);

		$flight = Flight::create([
			'airline_icao' => 'VIR', 'flight' => 'VS3', 'flight_no' => '3',
			'origin_icao' => 'EGLL', 'destination_icao' => 'KJFK',
			'departure_date' => $date->toDateString(),
		]);
		$watch = $flight->watch()->create(['enabled' => false]);

		$response = $this->actingAs($user, 'sanctum')
			->postJson('/api/watches', [
				'flight_number' => 'VS3',
				'origin' => 'EGLL',
				'destination' => 'KJFK',
				'date' => $date->toDateString(),
			]);

		$response->assertStatus(200);
		$response->assertJsonPath('watch.id', $watch->id);

		Http::assertNothingSent();
		$this->assertSame(1, Flight::where('flight', 'VS3')->count());
	}

	// =========================================================================
	public function test_watch_adds_a_second_listener_to_an_existing_watch(): void {
		$firstUser = User::factory()->create();
		$secondUser = User::factory()->create();
		$date = Carbon::now()->addDays(10);

		$flight = Flight::create([
			'airline_icao' => 'VIR', 'flight' => 'VS3', 'flight_no' => '3',
			'origin_icao' => 'EGLL', 'destination_icao' => 'KJFK',
			'departure_date' => $date->toDateString(),
		]);
		$watch = $flight->watch()->create(['enabled' => false]);
		$watch->listeners()->create(['user_id' => $firstUser->id, 'travelers' => $firstUser->name]);

		$response = $this->actingAs($secondUser, 'sanctum')
			->postJson('/api/watches', [
				'flight_number' => 'VS3',
				'origin' => 'EGLL',
				'destination' => 'KJFK',
				'date' => $date->toDateString(),
			]);

		$response->assertStatus(200);
		$this->assertSame(2, $watch->listeners()->count());
		$this->assertDatabaseHas('listeners', ['watch_id' => $watch->id, 'user_id' => $secondUser->id]);
	}

	// =========================================================================
	public function test_watch_is_idempotent_for_the_same_user(): void {
		$user = User::factory()->create();
		$this->fakeSchedule();

		$params = [
			'flight_number' => 'VS3',
			'origin' => 'EGLL',
			'destination' => 'KJFK',
			'date' => Carbon::now()->addDays(10)->toDateString(),
		];

		$this->actingAs($user, 'sanctum')->postJson('/api/watches', $params)->assertStatus(200);
		$this->actingAs($user, 'sanctum')->postJson('/api/watches', $params)->assertStatus(200);

		$this->assertSame(1, Flight::where('flight', 'VS3')->count());
		$this->assertSame(1, \App\Models\Listener::where('user_id', $user->id)->count());
	}

	// =========================================================================
	public function test_watch_returns_404_when_no_matching_scheduled_flight(): void {
		$user = User::factory()->create();

		Http::fake([
			'*/schedules/*' => Http::response(['scheduled' => []], 200),
		]);

		$response = $this->actingAs($user, 'sanctum')
			->postJson('/api/watches', [
				'flight_number' => 'VS3',
				'origin' => 'EGLL',
				'destination' => 'KJFK',
				'date' => Carbon::now()->addDays(10)->toDateString(),
			]);

		$response->assertStatus(404);
		$this->assertDatabaseMissing('flights', ['flight' => 'VS3']);
	}

	// =========================================================================
	public function test_watch_returns_502_when_aeroapi_unreachable(): void {
		$user = User::factory()->create();

		Http::fake(function () {
			throw new \Illuminate\Http\Client\ConnectionException('timed out');
		});

		$response = $this->actingAs($user, 'sanctum')
			->postJson('/api/watches', [
				'flight_number' => 'VS3',
				'origin' => 'EGLL',
				'destination' => 'KJFK',
				'date' => Carbon::now()->addDays(10)->toDateString(),
			]);

		$response->assertStatus(502);
	}

	// =========================================================================
	public function test_watch_validates_required_fields(): void {
		$user = User::factory()->create();

		$response = $this->actingAs($user, 'sanctum')
			->postJson('/api/watches', []);

		$response->assertStatus(422);
		$response->assertJsonValidationErrors(['flight_number', 'origin', 'destination', 'date']);
	}

	// =========================================================================
	public function test_watch_requires_authentication(): void {
		$response = $this->postJson('/api/watches', [
			'flight_number' => 'VS3',
			'origin' => 'EGLL',
			'destination' => 'KJFK',
			'date' => Carbon::now()->addDays(10)->toDateString(),
		]);

		$response->assertStatus(401);
	}

	// =========================================================================
	private function scheduleEntry(array $overrides = []): array {
		return array_merge([
			'ident_iata' => 'VS3',
			'ident_icao' => 'VIR3',
			'origin_icao' => 'EGLL',
			'origin_iata' => 'LHR',
			'destination_icao' => 'KJFK',
			'destination_iata' => 'JFK',
			'scheduled_out' => '2026-07-01T14:00:00Z',
			'scheduled_in' => '2026-07-01T22:05:00Z',
		], $overrides);
	}

	// =========================================================================
	private function searchReturning(array $entries) {
		Http::fake([
			'*/schedules/*' => Http::response(['scheduled' => $entries], 200),
		]);

		return $this->actingAs(User::factory()->create(), 'sanctum')
			->postJson('/api/flights/search', [
				'origin' => 'EGLL',
				'destination' => 'KJFK',
				'date' => Carbon::now()->addDays(10)->toDateString(),
			]);
	}

	// =========================================================================
	public function test_search_returns_departure_and_arrival_times(): void {
		$response = $this->searchReturning([$this->scheduleEntry()]);

		$response->assertStatus(200);
		$response->assertJsonCount(1);
		$response->assertJson([[
			'flight_number' => 'VS3',
			'departure_time' => '2026-07-01 14:00',
			'arrival_time' => '2026-07-01 22:05',
			'origin_name' => 'London Heathrow',
			'destination_name' => 'John F Kennedy Intl',
			'airline_name' => 'Virgin Atlantic',
		]]);
	}

	// =========================================================================
	// FlightAware sends null for an airport without an IATA code. That used
	// to throw a TypeError and fail the whole search.
	public function test_search_handles_a_missing_iata_code(): void {
		$response = $this->searchReturning([
			$this->scheduleEntry(),
			$this->scheduleEntry(['ident_iata' => 'VS4', 'destination_iata' => null]),
		]);

		$response->assertStatus(200);
		$response->assertJsonCount(2);
		$response->assertJsonPath('1.destination_iata', null);
		$response->assertJsonPath('1.destination_name', 'John F Kennedy Intl');
	}

	// =========================================================================
	public function test_search_falls_back_to_iata_without_an_icao_code(): void {
		$response = $this->searchReturning([$this->scheduleEntry(['origin_icao' => null])]);

		$response->assertStatus(200);
		$response->assertJsonPath('0.origin_name', 'London Heathrow');
	}

	// =========================================================================
	public function test_search_returns_empty_name_without_either_code(): void {
		$response = $this->searchReturning([
			$this->scheduleEntry(['origin_icao' => null, 'origin_iata' => null]),
		]);

		$response->assertStatus(200);
		$response->assertJsonPath('0.origin_name', '');
	}

	// =========================================================================
	public function test_search_prefers_icao_when_codes_disagree(): void {
		$response = $this->searchReturning([
			$this->scheduleEntry(['origin_icao' => 'EGLL', 'origin_iata' => 'JFK']),
		]);

		$response->assertStatus(200);
		$response->assertJsonPath('0.origin_name', 'London Heathrow');
	}
}
