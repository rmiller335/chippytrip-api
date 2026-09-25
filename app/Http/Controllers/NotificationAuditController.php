<?php

namespace App\Http\Controllers;

use App\Services\FlightEventAudit;
use App\Services\NotificationAuditSvc;
use Illuminate\Http\JsonResponse;

// =============================================================================
// GET /health/notifications — notifications:audit for monitoring. 200 with
// status "ok" or "warning", 503 with "critical" when any flight has an
// error, 404 without a valid X-Health-Token. See docs/api.md.
class NotificationAuditController extends Controller {
	// =========================================================================
	public function __invoke(NotificationAuditSvc $svc): JsonResponse {
		$flights = $svc->run();

		$severities = array_column(array_merge(...array_column($flights, 'findings')), 'severity');

		$errors = count(array_keys($severities, FlightEventAudit::ERROR));
		$warnings = count(array_keys($severities, FlightEventAudit::WARNING));

		$status = match (true) {
			$errors > 0 =>		'critical',
			$warnings > 0 =>	'warning',
			default =>			'ok',
		};

		return response()->json([
			'status' =>		$status,
			'checked_at' =>	now()->toIso8601String(),
			'errors' =>		$errors,
			'warnings' =>	$warnings,
			'flights' =>	$flights,
		], $errors > 0 ? 503 : 200);
	}
}
