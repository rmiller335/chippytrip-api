<?php

namespace App\Services;

use App\Console\Commands\MaintenanceNightly;
use App\Jobs\HealthHeartbeat;
use Carbon\Carbon;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// =============================================================================
// Each check returns ['status' => ok|warn|fail, 'message' => ..., ...details].
// A check that throws is reported as a failure by HealthController.
class HealthCheckSvc {
	public const OK =	'ok';
	public const WARN =	'warn';
	public const FAIL =	'fail';

	// =========================================================================
	private static function result(string $status, string $message, array $details = []): array {
		return array_merge(['status' => $status, 'message' => $message], $details);
	}

	// =========================================================================
	public function app(): array {
		if (empty(config('app.key'))) {
			return self::result(self::FAIL, 'APP_KEY is not set.');
		}

		if (app()->isProduction() && config('app.debug')) {
			return self::result(self::WARN, 'APP_DEBUG is on in production.');
		}

		return self::result(self::OK, 'Configured.', ['env' => app()->environment()]);
	}

	// =========================================================================
	public function database(): array {
		DB::select('select 1');

		return self::result(self::OK, 'Connected.', [
			'connection' =>	config('database.default'),
		]);
	}

	// =========================================================================
	// Code deployed without `php artisan migrate` fails in odd ways, so treat
	// pending migrations as a failure.
	public function migrations(): array {
		$migrator = app('migrator');

		if (! $migrator->repositoryExists()) {
			return self::result(self::FAIL, 'The migrations table is missing.');
		}

		$files = $migrator->getMigrationFiles(
			array_merge([database_path('migrations')], $migrator->paths())
		);
		$ran = $migrator->getRepository()->getRan();
		$pending = array_values(array_diff(array_keys($files), $ran));

		if ($pending) {
			return self::result(self::FAIL, count($pending) . ' migration(s) not run.', [
				'pending' =>	$pending,
			]);
		}

		return self::result(self::OK, 'Up to date.');
	}

	// =========================================================================
	public function cache(): array {
		$key = 'health:cache:' . Str::random(16);
		$value = Str::random(16);

		Cache::put($key, $value, 60);
		$read = Cache::get($key);
		Cache::forget($key);

		if ($read !== $value) {
			return self::result(self::FAIL, 'Wrote a value but read back something else.');
		}

		return self::result(self::OK, 'Read and write work.', ['store' => config('cache.default')]);
	}

	// =========================================================================
	public function storage(): array {
		$disk = Storage::disk();
		$path = 'health/' . Str::random(16) . '.txt';
		$value = Str::random(16);

		$disk->put($path, $value);
		$read = $disk->get($path);
		$disk->delete($path);

		if ($read !== $value) {
			return self::result(self::FAIL, 'Wrote a file but read back something else.');
		}

		$unwritable = array_values(array_filter([
			storage_path('logs'),
			storage_path('framework/cache'),
			base_path('bootstrap/cache'),
		], fn ($dir) => ! is_writable($dir)));

		if ($unwritable) {
			return self::result(self::FAIL, 'Directories not writable.', ['unwritable' => $unwritable]);
		}

		$freeMb = (int) (disk_free_space(storage_path()) / 1024 / 1024);

		if ($freeMb < config('health.min_free_disk_mb')) {
			return self::result(self::FAIL, "Only {$freeMb} MB of disk free.", ['free_mb' => $freeMb]);
		}

		return self::result(self::OK, 'Writable.', ['free_mb' => $freeMb]);
	}

	// =========================================================================
	// Dispatches a HealthHeartbeat (at most once a minute) so there's always
	// a job for the worker to pick up, then fails if any job has waited
	// longer than health.queue_max_wait. A stopped worker shows up within
	// that time as long as /health keeps being polled.
	public function queue(): array {
		$connection = config('queue.default');
		$driver = config("queue.connections.{$connection}.driver");

		if ($driver === 'sync') {
			return self::result(self::OK, 'Jobs run synchronously; no worker needed.');
		}

		if (Cache::add('health:heartbeat_dispatched', true, 60)) {
			HealthHeartbeat::dispatch();
		}

		$lastHeartbeat = Cache::get(HealthHeartbeat::CACHE_KEY);

		if ($driver !== 'database') {
			return self::result(self::OK, 'Heartbeat dispatched.', [
				'pending' =>		Queue::size(),
				'last_heartbeat' =>	$lastHeartbeat,
			]);
		}

		$maxWait = config('health.queue_max_wait');
		$jobs = DB::connection(config("queue.connections.{$connection}.connection"))
			->table(config("queue.connections.{$connection}.table", 'jobs'));

		$pending = (clone $jobs)->whereNull('reserved_at')->count();
		$stuck = (clone $jobs)
			->whereNull('reserved_at')
			->where('available_at', '<=', now()->subSeconds($maxWait)->getTimestamp())
			->count();

		$details = [
			'pending' =>		$pending,
			'last_heartbeat' =>	$lastHeartbeat,
		];

		if ($stuck > 0) {
			return self::result(self::FAIL,
				"{$stuck} job(s) waiting over {$maxWait}s; is the queue worker running?",
				$details
			);
		}

		return self::result(self::OK, 'Worker is processing jobs.', $details);
	}

	// =========================================================================
	public function failedJobs(): array {
		$failed = DB::connection(config('queue.failed.database'))
			->table(config('queue.failed.table', 'failed_jobs'))
			->where('failed_at', '>=', now()->subDay())
			->count();

		if ($failed > 0) {
			return self::result(self::WARN, "{$failed} job(s) failed in the last 24 hours.", [
				'failed_24h' =>	$failed,
			]);
		}

		return self::result(self::OK, 'No failed jobs in the last 24 hours.', ['failed_24h' => 0]);
	}

	// =========================================================================
	// Enables/disables watches and prunes FlightAware alerts; if cron stops
	// running it, notifications quietly stop.
	public function maintenance(): array {
		$lastRun = Cache::get(MaintenanceNightly::LAST_RUN_KEY);

		if (null === $lastRun) {
			return self::result(self::WARN, 'No completed maintenance:nightly run recorded yet.');
		}

		$age = (int) Carbon::parse($lastRun)->diffInSeconds(now());
		$details = ['last_run' => $lastRun];

		if ($age > config('health.maintenance_max_age')) {
			return self::result(self::FAIL, "maintenance:nightly last finished {$age}s ago.", $details);
		}

		return self::result(self::OK, 'Running on schedule.', $details);
	}

	// =========================================================================
	// Watch creation, flight search and callbacks all depend on AeroAPI.
	// /account/usage checks the key without using a billed query.
	public function flightAware(): array {
		foreach (['url', 'key', 'callback'] as $setting) {
			if (empty(config("flightaware.{$setting}"))) {
				return self::result(self::FAIL, "flightaware.{$setting} is not configured.");
			}
		}

		$resp = Http::timeout(config('health.http_timeout'))
			->withHeaders(['x-apikey' => config('flightaware.key')])
			->get(config('flightaware.url') . '/account/usage');

		if (! $resp->successful()) {
			return self::result(self::FAIL, "AeroAPI returned {$resp->status()}.");
		}

		return self::result(self::OK, 'AeroAPI accepted the key.');
	}

	// =========================================================================
	// Gets an OAuth token from the service account, then has FCM validate
	// (but not send) a message to a topic nobody subscribes to.
	public function fcm(): array {
		$projectId = config('push.project_id');
		$credentials = config('push.credentials');

		if (empty($projectId)) {
			return self::result(self::FAIL, 'push.project_id (FCM_PROJECT_ID) is not configured.');
		}

		// FcmSender uses the path as-is, and the queue worker runs from the
		// project root, so resolve relative paths against that.
		if (! empty($credentials) && ! Str::startsWith($credentials, '/')) {
			$credentials = base_path($credentials);
		}

		if (empty($credentials) || ! is_readable($credentials)) {
			return self::result(self::FAIL, 'FCM service-account JSON is missing or unreadable.');
		}

		$resp = Http::timeout(config('health.http_timeout'))
			->withToken($this->fcmAccessToken($credentials))
			->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
				'validate_only' =>	true,
				'message' =>		[
					'topic' =>			'health-check',
					'notification' =>	['title' => 'Health check', 'body' => 'Not delivered.'],
				],
			]);

		if (! $resp->successful()) {
			return self::result(self::FAIL, "FCM returned {$resp->status()}.");
		}

		return self::result(self::OK, 'FCM accepted the credentials.');
	}

	// =========================================================================
	protected function fcmAccessToken(string $credentialsPath): string {
		$credentials = new ServiceAccountCredentials(
			'https://www.googleapis.com/auth/firebase.messaging',
			$credentialsPath
		);

		return $credentials->fetchAuthToken()['access_token']
			?? throw new \RuntimeException('Could not fetch an FCM access token.');
	}

	// =========================================================================
	// Used to read forwarded confirmation emails. Also confirms the
	// configured model exists.
	public function openAi(): array {
		if (empty(config('services.openai.key'))) {
			return self::result(self::FAIL, 'OPENAI_API_KEY is not configured.');
		}

		$model = config('services.openai.flight_model');

		$resp = Http::timeout(config('health.http_timeout'))
			->withToken(config('services.openai.key'))
			->get("https://api.openai.com/v1/models/{$model}");

		if (! $resp->successful()) {
			return self::result(self::FAIL, "OpenAI returned {$resp->status()} for model {$model}.");
		}

		return self::result(self::OK, "Model {$model} is available.");
	}
}
