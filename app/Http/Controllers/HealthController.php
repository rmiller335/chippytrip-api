<?php

namespace App\Http\Controllers;

use App\Services\HealthCheckSvc;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

// =============================================================================
// GET /health — 200 when everything critical works, 503 when anything
// critical fails, 404 without a valid X-Health-Token (RequireHealthToken).
// See docs/api.md.
class HealthController extends Controller {
	// Check name => [HealthCheckSvc method, critical, external]. A failed
	// non-critical check makes the status "degraded", not "down". External
	// checks call paid third-party APIs, so their results are cached.
	private const CHECKS = [
		'app' =>			['app', true, false],
		'database' =>		['database', true, false],
		'migrations' =>		['migrations', true, false],
		'cache' =>			['cache', true, false],
		'storage' =>		['storage', true, false],
		'queue' =>			['queue', true, false],
		'failed_jobs' =>	['failedJobs', false, false],
		'maintenance' =>	['maintenance', true, false],
		'flightaware' =>	['flightAware', true, true],
		'fcm' =>			['fcm', true, true],
		'openai' =>			['openAi', false, true],
	];

	// =========================================================================
	public function __invoke(HealthCheckSvc $svc): JsonResponse {
		$checks = [];

		foreach (self::CHECKS as $name => [$method, $critical, $external]) {
			$result = $external
				? $this->cachedCheck($name, fn () => $this->runCheck($svc, $method))
				: $this->runCheck($svc, $method);

			if (! $critical && $result['status'] === HealthCheckSvc::FAIL) {
				$result['status'] = HealthCheckSvc::WARN;
			}

			$checks[$name] = $result;
		}

		$statuses = array_column($checks, 'status');
		$status = match (true) {
			in_array(HealthCheckSvc::FAIL, $statuses) =>	'down',
			in_array(HealthCheckSvc::WARN, $statuses) =>	'degraded',
			default =>										'ok',
		};

		if ($status !== 'ok') {
			Log::warning("HealthController: status {$status}", array_filter(
				$checks, fn ($c) => $c['status'] !== HealthCheckSvc::OK
			));
		}

		return response()->json([
			'status' =>		$status,
			'checked_at' =>	now()->toIso8601String(),
			'checks' =>		$checks,
		], $status === 'down' ? 503 : 200);
	}

	// =========================================================================
	private function runCheck(HealthCheckSvc $svc, string $method): array {
		$start = hrtime(true);

		try {
			$result = $svc->$method();
		}
		catch (\Throwable $e) {
			$result = [
				'status' =>		HealthCheckSvc::FAIL,
				'message' =>	class_basename($e) . ': ' . $e->getMessage(),
			];
		}

		$result['ms'] = (int) ((hrtime(true) - $start) / 1_000_000);

		return $result;
	}

	// =========================================================================
	// Passing results are cached for health.external_ttl, failures for a
	// minute so a recovery shows up quickly. If the cache itself is down,
	// skip the check rather than call the API on every request.
	private function cachedCheck(string $name, callable $check): array {
		$key = "health:external:{$name}";

		try {
			$cached = Cache::get($key);

			if (null !== $cached) {
				return $cached + ['cached' => true];
			}

			$result = $check();
			$ttl = $result['status'] === HealthCheckSvc::OK ? config('health.external_ttl') : 60;
			Cache::put($key, $result + ['checked_at' => now()->toIso8601String()], $ttl);

			return $result;
		}
		catch (\Throwable $e) {
			return [
				'status' =>		HealthCheckSvc::WARN,
				'message' =>	'Not checked: the cache is unavailable.',
			];
		}
	}
}
