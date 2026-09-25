<?php

namespace App\Console\Commands;

use App\Services\FlightEventAudit;
use App\Services\NotificationAuditSvc;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

// =============================================================================
class NotificationsAudit extends Command {
	protected $signature = 'notifications:audit {--hours= : Audit flights departing this many hours back (default health.audit.lookback_hours)}';
	protected $description = 'Check recent flights for missing or out-of-order notifications';

	// =========================================================================
	// Exits 1 if any flight has an error, so cron can mail on failure.
	public function handle(NotificationAuditSvc $svc): int {
		$hours = $this->option('hours');
		$results = $svc->run(lookbackHours: null === $hours ? null : (int) $hours);

		$rows = [];
		$errors = false;

		foreach ($results as $r) {
			foreach ($r['findings'] as $f) {
				$errors = $errors || $f['severity'] === FlightEventAudit::ERROR;

				$rows[] = [
					$r['watch_id'], $r['flight'], $r['departure_date'],
					$f['severity'], $f['code'], $f['callback_id'], $f['message'],
				];

				$level = $f['severity'] === FlightEventAudit::ERROR ? 'error' : 'warning';
				Log::$level("notifications:audit {$r['flight']} {$r['departure_date']}: {$f['message']}", [
					'watch_id' =>		$r['watch_id'],
					'code' =>			$f['code'],
					'callback_id' =>	$f['callback_id'],
				]);
			}
		}

		if (empty($rows)) {
			$this->info('No problems found.');
			return self::SUCCESS;
		}

		$this->table(['Watch', 'Flight', 'Date', 'Severity', 'Code', 'Callback', 'Message'], $rows);

		return $errors ? self::FAILURE : self::SUCCESS;
	}
}
