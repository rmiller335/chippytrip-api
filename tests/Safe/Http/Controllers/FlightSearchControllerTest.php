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
// Covers FlightSearchController::watch() — checkFlight()/search() are
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
}
