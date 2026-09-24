<?php

namespace Tests\Safe\Http\Controllers;

use App\Console\Commands\MaintenanceNightly;
use App\Services\HealthCheckSvc;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Safe\TestCase;

// =============================================================================
class HealthControllerTest extends TestCase {
	private string $credentialsPath;

	// =========================================================================
	protected function setUp(): void {
		parent::setUp();

		$this->credentialsPath = tempnam(sys_get_temp_dir(), 'fcm');
		file_put_contents($this->credentialsPath, '{}');

		config([
			'health.token' =>				'health-secret',
			'flightaware.url' =>			'https://aeroapi.test',
			'flightaware.key' =>			'fa-key',
			'flightaware.callback' =>		'/api/watch-callback',
			'push.project_id' =>			'chippy-test',
			'push.credentials' =>			$this->credentialsPath,
			'services.openai.key' =>		'openai-key',
			'services.openai.flight_model' => 'gpt-test',
		]);

		// google/auth uses its own HTTP client, which Http::fake can't see.
		$svc = \Mockery::mock(HealthCheckSvc::class)
			->makePartial()
			->shouldAllowMockingProtectedMethods();
		$svc->shouldReceive('fcmAccessToken')->andReturn('fcm-token');
		$this->app->instance(HealthCheckSvc::class, $svc);

		Cache::forever(MaintenanceNightly::LAST_RUN_KEY, now()->subMinutes(30)->toIso8601String());
	}

	// =========================================================================
	protected function tearDown(): void {
		@unlink($this->credentialsPath);

		parent::tearDown();
	}

	// =========================================================================
	private function fakeExternals(array $overrides = []): void {
		Http::fake(array_merge([
			'aeroapi.test/account/usage' =>	Http::response(['total_cost' => 0], 200),
			'fcm.googleapis.com/*' =>		Http::response(['name' => 'projects/chippy-test/messages/fake'], 200),
			'api.openai.com/v1/models/*' =>	Http::response(['id' => 'gpt-test'], 200),
		], $overrides));
	}

	// =========================================================================
	private function getHealth(bool $withToken = true) {
		return $this->getJson('/health', $withToken ? ['X-Health-Token' => 'health-secret'] : []);
	}

	// =========================================================================
	public function test_all_checks_pass(): void {
		$this->fakeExternals();

		$response = $this->getHealth();

		$response->assertStatus(200);
		$response->assertJsonPath('status', 'ok');
		foreach (['app', 'database', 'migrations', 'cache', 'storage', 'queue', 'failed_jobs',
				'maintenance', 'flightaware', 'fcm', 'openai'] as $check) {
			$response->assertJsonPath("checks.{$check}.status", 'ok');
		}

		Http::assertSent(fn ($request) => $request->url() === 'https://aeroapi.test/account/usage'
			&& $request->hasHeader('x-apikey', 'fa-key'));
		Http::assertSent(fn ($request) => str_contains($request->url(), 'projects/chippy-test/messages:send')
			&& $request['validate_only'] === true);
	}

	// =========================================================================
	public function test_details_are_hidden_without_the_token(): void {
		$this->fakeExternals();

		$response = $this->getHealth(withToken: false);

		$response->assertStatus(200);
		$response->assertExactJson([
			'status' => 'ok',
			'checked_at' => $response->json('checked_at'),
			'checks' => collect($response->json('checks'))->map(fn () => ['status' => 'ok'])->all(),
		]);
	}

	// =========================================================================
	public function test_details_are_hidden_when_no_token_is_configured(): void {
		config(['health.token' => null]);
		$this->fakeExternals();

		$response = $this->getJson('/health', ['X-Health-Token' => '']);

		$response->assertJsonMissingPath('checks.database.message');
	}

	// =========================================================================
	public function test_critical_failure_returns_503(): void {
		$this->fakeExternals([
			'aeroapi.test/account/usage' => Http::response(['title' => 'Unauthorized'], 401),
		]);

		$response = $this->getHealth();

		$response->assertStatus(503);
		$response->assertJsonPath('status', 'down');
		$response->assertJsonPath('checks.flightaware.status', 'fail');
		$response->assertJsonPath('checks.flightaware.message', 'AeroAPI returned 401.');
	}

	// =========================================================================
	public function test_non_critical_failure_is_degraded_not_down(): void {
		$this->fakeExternals([
			'api.openai.com/v1/models/*' => Http::response(['error' => 'nope'], 404),
		]);

		$response = $this->getHealth();

		$response->assertStatus(200);
		$response->assertJsonPath('status', 'degraded');
		$response->assertJsonPath('checks.openai.status', 'warn');
	}

	// =========================================================================
	public function test_external_results_are_cached(): void {
		$this->fakeExternals();

		$this->getHealth()->assertStatus(200);
		$response = $this->getHealth();

		$response->assertJsonPath('checks.flightaware.cached', true);
		Http::assertSentCount(3);
	}

	// =========================================================================
	public function test_missing_fcm_credentials_fail(): void {
		config(['push.credentials' => '/nonexistent/service-account.json']);
		$this->fakeExternals();

		$response = $this->getHealth();

		$response->assertStatus(503);
		$response->assertJsonPath('checks.fcm.status', 'fail');
	}

	// =========================================================================
	public function test_relative_fcm_credentials_resolve_from_the_project_root(): void {
		$relative = 'storage/framework/testing-fcm.json';
		file_put_contents(base_path($relative), '{}');
		config(['push.credentials' => $relative]);
		$this->fakeExternals();

		try {
			$this->getHealth()->assertJsonPath('checks.fcm.status', 'ok');
		}
		finally {
			@unlink(base_path($relative));
		}
	}

	// =========================================================================
	public function test_stale_maintenance_run_fails(): void {
		Cache::forever(MaintenanceNightly::LAST_RUN_KEY, now()->subHours(3)->toIso8601String());
		$this->fakeExternals();

		$response = $this->getHealth();

		$response->assertStatus(503);
		$response->assertJsonPath('checks.maintenance.status', 'fail');
	}

	// =========================================================================
	public function test_missing_maintenance_run_warns(): void {
		Cache::forget(MaintenanceNightly::LAST_RUN_KEY);
		$this->fakeExternals();

		$response = $this->getHealth();

		$response->assertStatus(200);
		$response->assertJsonPath('checks.maintenance.status', 'warn');
	}

	// =========================================================================
	public function test_maintenance_nightly_records_its_run(): void {
		Cache::forget(MaintenanceNightly::LAST_RUN_KEY);
		$fa = \Mockery::mock(\App\Services\FlightAwareSvc::class);
		$fa->shouldReceive('watchList')->andReturn([]);
		$this->app->instance(\App\Services\FlightAwareSvc::class, $fa);

		$this->artisan('maintenance:nightly')->assertExitCode(0);

		$this->assertNotNull(Cache::get(MaintenanceNightly::LAST_RUN_KEY));
	}

	// =========================================================================
	public function test_queue_fails_when_jobs_are_stuck(): void {
		config(['queue.default' => 'database']);
		DB::table('jobs')->insert([
			'queue' => 'default',
			'payload' => '{}',
			'attempts' => 0,
			'available_at' => now()->subMinutes(10)->getTimestamp(),
			'created_at' => now()->subMinutes(10)->getTimestamp(),
		]);
		$this->fakeExternals();

		$response = $this->getHealth();

		$response->assertStatus(503);
		$response->assertJsonPath('checks.queue.status', 'fail');
		// The stuck job plus the heartbeat the check dispatched.
		$response->assertJsonPath('checks.queue.pending', 2);
	}

	// =========================================================================
	public function test_queue_passes_and_dispatches_a_heartbeat(): void {
		config(['queue.default' => 'database']);
		$this->fakeExternals();

		$response = $this->getHealth();

		$response->assertJsonPath('checks.queue.status', 'ok');
		$this->assertSame(1, DB::table('jobs')->count());

		// Only one heartbeat a minute.
		$this->getHealth();
		$this->assertSame(1, DB::table('jobs')->count());
	}

	// =========================================================================
	public function test_recent_failed_jobs_warn(): void {
		DB::table('failed_jobs')->insert([
			'uuid' => (string) \Illuminate\Support\Str::uuid(),
			'connection' => 'database',
			'queue' => 'default',
			'payload' => '{}',
			'exception' => 'boom',
			'failed_at' => now()->subHour(),
		]);
		$this->fakeExternals();

		$response = $this->getHealth();

		$response->assertStatus(200);
		$response->assertJsonPath('status', 'degraded');
		$response->assertJsonPath('checks.failed_jobs.failed_24h', 1);
	}
}
