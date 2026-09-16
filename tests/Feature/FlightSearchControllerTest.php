<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

// =============================================================================
class FlightSearchControllerTest extends TestCase {
	private static bool $synced = false;

	// =========================================================================
	protected function setUp(): void {
		parent::setUp();

		Log::debug('FlightSearchControllerTest: setUp()');

		if (! self::$synced) {
			exec(base_path('bin/sync-test-db'), $output, $exitCode);
			if ($exitCode !== 0) {
				$this->fail('bin/sync-test-db failed: ' . implode("\n", $output));
			}
			self::$synced = true;
		}
	}

    // =========================================================================
    // Helpers
    // =========================================================================

	/**
	 * Build a single AeroAPI-style flight object, as returned inside the
	 * "flights" array of both /flights/{ident} and the airport-to-airport
	 * route endpoint. Mirrors the fields FlightSearchController::normalize()
	 * reads.
	 */
	protected function buildAeroApiFlight(array $overrides = []): array {
		return array_merge([
			'ident'          => 'VS3',
			'ident_iata'     => 'VS3',
			'ident_icao'     => 'VIR3',
			'operator'       => 'VIR',
			'operator_iata'  => 'VS',
			'flight_number'  => '3',
			'scheduled_out'  => Carbon::now()->addDay()->setTime(14, 0, 0)->toIso8601ZuluString(),
			'origin'         => [
				'code'      => 'EGLL',
				'code_icao' => 'EGLL',
				'code_iata' => 'LHR',
				'name'      => 'London Heathrow',
				'city'      => 'London',
			],
			'destination'    => [
				'code'      => 'KJFK',
				'code_icao' => 'KJFK',
				'code_iata' => 'JFK',
				'name'      => 'John F Kennedy Intl',
				'city'      => 'New York',
			],
		], $overrides);
	}

    // =========================================================================
    // check() — flight number + date
    // =========================================================================

	public function test_check_returns_normalized_flight_on_match(): void {
		$user = User::query()->firstOrFail();
		$date = Carbon::now()->addDay();

		Http::fake([
			config('flightaware.url') . '/flights/*' => Http::response([
				'flights' => [
					$this->buildAeroApiFlight([
						'scheduled_out' => $date->copy()->setTime(14, 0, 0)->toIso8601ZuluString(),
					]),
				],
			], 200),
		]);

		$response = $this->actingAs($user, 'sanctum')
			->postJson('/api/flights/validate', [
				'flight_number' => 'VS3',
				'date'          => $date->toDateString(),
			]);

		$response->assertStatus(200);
		$response->assertJson([
			'flight_number'    => 'VS3',
			'airline_icao'     => 'VIR',
			'airline_iata'     => 'VS',
			'origin_icao'      => 'EGLL',
			'origin'           => 'London',
			'destination_icao' => 'KJFK',
			'destination'      => 'New York',
		]);
	}

	/**
	 * flightInfo() now returns null on a 404 from AeroAPI — the controller
	 * should turn that straight into a 404 without touching ->flights.
	 */
	public function test_check_returns_404_when_flight_not_found(): void {
		$user = User::query()->firstOrFail();

		Http::fake([
			config('flightaware.url') . '/flights/*' => Http::response(null, 404),
		]);

		$response = $this->actingAs($user, 'sanctum')
			->postJson('/api/flights/validate', [
				'flight_number' => 'ZZ999',
				'date'          => Carbon::now()->addDay()->toDateString(),
			]);

		$response->assertStatus(404);
	}

	public function test_check_returns_502_when_aeroapi_unreachable(): void {
		$user = User::query()->firstOrFail();

		Http::fake([
			config('flightaware.url') . '/flights/*' => function () {
				throw new ConnectionException('timed out');
			},
		]);

		$response = $this->actingAs($user, 'sanctum')
			->postJson('/api/flights/validate', [
				'flight_number' => 'VS3',
				'date'          => Carbon::now()->addDay()->toDateString(),
			]);

		$response->assertStatus(502);
	}

	public function test_check_requires_authentication(): void {
		$response = $this->postJson('/api/flights/validate', [
			'flight_number' => 'VS3',
			'date'          => Carbon::now()->addDay()->toDateString(),
		]);

		$response->assertStatus(401);
	}

	public function test_check_validates_required_fields(): void {
		$user = User::query()->firstOrFail();

		$response = $this->actingAs($user, 'sanctum')
			->postJson('/api/flights/validate', []);

		$response->assertStatus(422);
		$response->assertJsonValidationErrors(['flight_number', 'date']);
	}

    // =========================================================================
    // search() — route + date, optional airline
    // =========================================================================

	public function test_search_returns_only_flights_matching_the_date(): void {
		$user = User::query()->firstOrFail();
		$date = Carbon::now()->addDays(2);

		Http::fake([
			config('flightaware.url') . '/airports/*/flights/to/*' => Http::response([
				'flights' => [
					// Matches the requested date
					$this->buildAeroApiFlight([
						'ident'         => 'VS3',
						'ident_iata'    => 'VS3',
						'scheduled_out' => $date->copy()->setTime(9, 0, 0)->toIso8601ZuluString(),
					]),
					// A week later — should be filtered out
					$this->buildAeroApiFlight([
						'ident'         => 'VS5',
						'ident_iata'    => 'VS5',
						'scheduled_out' => $date->copy()->addWeek()->setTime(9, 0, 0)->toIso8601ZuluString(),
					]),
				],
			], 200),
		]);

		$response = $this->actingAs($user, 'sanctum')
			->postJson('/api/flights/search', [
				'origin'      => 'EGLL',
				'destination' => 'KJFK',
				'date'        => $date->toDateString(),
			]);

		$response->assertStatus(200);
		$response->assertJsonCount(1);
		$response->assertJsonFragment(['flight_number' => 'VS3']);
	}

	public function test_search_filters_by_optional_airline(): void {
		$user = User::query()->firstOrFail();
		$date = Carbon::now()->addDays(2);

		Http::fake([
			config('flightaware.url') . '/airports/*/flights/to/*' => Http::response([
				'flights' => [
					$this->buildAeroApiFlight([
						'ident'         => 'VS3',
						'ident_iata'    => 'VS3',
						'operator'      => 'VIR',
						'scheduled_out' => $date->copy()->setTime(9, 0, 0)->toIso8601ZuluString(),
					]),
					$this->buildAeroApiFlight([
						'ident'         => 'BA117',
						'ident_iata'    => 'BA117',
						'operator'      => 'BAW',
						'scheduled_out' => $date->copy()->setTime(10, 0, 0)->toIso8601ZuluString(),
					]),
				],
			], 200),
		]);

		$response = $this->actingAs($user, 'sanctum')
			->postJson('/api/flights/search', [
				'origin'      => 'EGLL',
				'destination' => 'KJFK',
				'date'        => $date->toDateString(),
				'airline'     => 'BAW',
			]);

		$response->assertStatus(200);
		$response->assertJsonCount(1);
		$response->assertJsonFragment(['flight_number' => 'BA117']);
	}

	public function test_search_returns_empty_array_when_nothing_matches(): void {
		$user = User::query()->firstOrFail();
		$date = Carbon::now()->addDays(2);

		Http::fake([
			config('flightaware.url') . '/airports/*/flights/to/*' => Http::response([
				'flights' => [],
			], 200),
		]);

		$response = $this->actingAs($user, 'sanctum')
			->postJson('/api/flights/search', [
				'origin'      => 'EGLL',
				'destination' => 'KJFK',
				'date'        => $date->toDateString(),
			]);

		$response->assertStatus(200);
		$response->assertJsonCount(0);
	}

	public function test_search_returns_502_when_aeroapi_fails(): void {
		$user = User::query()->firstOrFail();

		Http::fake([
			config('flightaware.url') . '/airports/*/flights/to/*' => Http::response('Server error', 500),
		]);

		$response = $this->actingAs($user, 'sanctum')
			->postJson('/api/flights/search', [
				'origin'      => 'EGLL',
				'destination' => 'KJFK',
				'date'        => Carbon::now()->addDay()->toDateString(),
			]);

		$response->assertStatus(502);
	}

	public function test_search_requires_authentication(): void {
		$response = $this->postJson('/api/flights/search', [
			'origin'      => 'EGLL',
			'destination' => 'KJFK',
			'date'        => Carbon::now()->addDay()->toDateString(),
		]);

		$response->assertStatus(401);
	}
}
