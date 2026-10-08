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
	 * Build a single AeroAPI /schedules entry, the shape FlightAwareSvc's
	 * scheduleForIdent() and scheduleByRoute() read and normalize() maps.
	 * Departures are mid-day UTC so the origin's local date is the same day.
	 */
	protected function buildScheduledFlight(array $overrides = []): array {
		return array_merge([
			'ident'            => 'VIR3',
			'ident_icao'       => 'VIR3',
			'ident_iata'       => 'VS3',
			'actual_ident'     => null,
			'scheduled_out'    => Carbon::now()->addDay()->setTime(14, 0, 0)->toIso8601ZuluString(),
			'scheduled_in'     => Carbon::now()->addDay()->setTime(22, 0, 0)->toIso8601ZuluString(),
			'origin'           => 'EGLL',
			'origin_icao'      => 'EGLL',
			'origin_iata'      => 'LHR',
			'destination'      => 'KJFK',
			'destination_icao' => 'KJFK',
			'destination_iata' => 'JFK',
		], $overrides);
	}

	protected function schedulesUrl(): string {
		return config('flightaware.url') . '/schedules/*';
	}

    // =========================================================================
    // check() — flight number + date
    // =========================================================================

	public function test_check_returns_normalized_flight_on_match(): void {
		$user = User::query()->firstOrFail();
		$date = Carbon::now()->addDay();

		Http::fake([
			$this->schedulesUrl() => Http::response([
				'scheduled' => [
					$this->buildScheduledFlight([
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
		$response->assertJsonCount(1);
		$response->assertJsonFragment([
			'flight_number'    => 'VS3',
			'flight_no'        => '3',
			'airline_icao'     => 'VIR',
			'airline_iata'     => 'VS',
			'origin_icao'      => 'EGLL',
			'origin_iata'      => 'LHR',
			'destination_icao' => 'KJFK',
			'destination_iata' => 'JFK',
		]);
	}

	public function test_check_returns_empty_when_flight_not_found(): void {
		$user = User::query()->firstOrFail();

		Http::fake([
			$this->schedulesUrl() => Http::response(['scheduled' => []], 200),
		]);

		$response = $this->actingAs($user, 'sanctum')
			->postJson('/api/flights/validate', [
				'flight_number' => 'ZZ999',
				'date'          => Carbon::now()->addDay()->toDateString(),
			]);

		$response->assertStatus(200);
		$response->assertJsonCount(0);
	}

	public function test_check_ignores_the_same_flight_on_the_next_day(): void {
		$user = User::query()->firstOrFail();
		$date = Carbon::now()->addDay();

		Http::fake([
			$this->schedulesUrl() => Http::response([
				'scheduled' => [
					$this->buildScheduledFlight([
						'scheduled_out' => $date->copy()->setTime(14, 0, 0)->toIso8601ZuluString(),
					]),
					$this->buildScheduledFlight([
						'scheduled_out' => $date->copy()->addDay()->setTime(14, 0, 0)->toIso8601ZuluString(),
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
		$response->assertJsonCount(1);
	}

	public function test_check_returns_502_when_aeroapi_unreachable(): void {
		$user = User::query()->firstOrFail();

		Http::fake([
			$this->schedulesUrl() => function () {
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
			$this->schedulesUrl() => Http::response([
				'scheduled' => [
					// Matches the requested date
					$this->buildScheduledFlight([
						'ident_iata'    => 'VS3',
						'scheduled_out' => $date->copy()->setTime(9, 0, 0)->toIso8601ZuluString(),
					]),
					// A week later — should be filtered out
					$this->buildScheduledFlight([
						'ident'         => 'VIR5',
						'ident_icao'    => 'VIR5',
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

		// The airline filter is part of the AeroAPI query, not applied locally.
		Http::fake([
			$this->schedulesUrl() => Http::response([
				'scheduled' => [
					$this->buildScheduledFlight([
						'ident'         => 'BAW117',
						'ident_icao'    => 'BAW117',
						'ident_iata'    => 'BA117',
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
		Http::assertSent(fn ($request) => str_contains($request->url(), 'airline=BAW'));
	}

	public function test_search_returns_empty_array_when_nothing_matches(): void {
		$user = User::query()->firstOrFail();
		$date = Carbon::now()->addDays(2);

		Http::fake([
			$this->schedulesUrl() => Http::response([
				'scheduled' => [],
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
			$this->schedulesUrl() => Http::response('Server error', 500),
		]);

		$response = $this->actingAs($user, 'sanctum')
			->postJson('/api/flights/search', [
				'origin'      => 'EGLL',
				'destination' => 'KJFK',
				'date'        => Carbon::now()->addDay()->toDateString(),
			]);

		$response->assertStatus(502);
	}

    // =========================================================================
    // watch() — the app sends a UTC departure timestamp
    // =========================================================================

	public function test_watch_uses_the_origins_local_date(): void {
		$user = User::query()->firstOrFail();

		// 00:35 UTC is 20:35 the evening before in Rochester (UTC-4/-5).
		$utc = Carbon::now('UTC')->addDays(2)->setTime(0, 35, 0);
		$local = $utc->copy()->setTimezone('America/New_York')->toDateString();

		$this->mock(\App\Services\FlightWatchSvc::class, function ($mock) use ($local) {
			$mock->shouldReceive('findOrCreateFlight')
				->once()
				->withArgs(fn ($ident, $origin, $destination, $date) => $date === $local)
				->andReturn(null);
		});

		$this->actingAs($user, 'sanctum')
			->postJson('/api/watches', [
				'flight_number' => 'DL2806',
				'origin'        => 'KROC',
				'destination'   => 'KATL',
				'date'          => $utc->toIso8601ZuluString(),
			])
			->assertStatus(404);
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
