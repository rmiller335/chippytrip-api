<?php

namespace App\Services;

use App\Models\Watch;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

// =============================================================================
// Runs FlightEventAudit over every recently active watch. Used by
// notifications:audit and GET /health/notifications.
class NotificationAuditSvc {
	// =========================================================================
	public function __construct(private readonly FlightEventAudit $audit) {
	}

	// =========================================================================
	// Returns one entry per watch with findings, each with the watch's
	// flight and a list of FlightEventAudit findings.
	public function run(?Carbon $now = null, ?int $lookbackHours = null): array {
		$now ??= Carbon::now('UTC');
		$lookbackHours ??= config('health.audit.lookback_hours');

		$watches = Watch::with('flight.origin')
			->whereHas('flight', fn (Builder $q) => $q
				->whereDate('departure_date', '>=', $now->copy()->subHours($lookbackHours)->toDateString())
				->whereDate('departure_date', '<=', $now->copy()->addDay()->toDateString())
			)
			// A watch whose alert never existed can't have missed anything.
			->where(fn (Builder $q) => $q
				->where('enabled', true)
				->orWhereNotNull('enabled_at')
				->orWhereHas('callbacks')
			)
			->orderBy('id')
		;

		$results = [];

		foreach ($watches->lazy(100) as $watch) {
			$findings = $this->auditWatch($watch, $now);

			if ($findings) {
				$results[] = [
					'watch_id' =>		$watch->id,
					'flight_id' =>		$watch->flight->id,
					'flight' =>			$watch->flight->flight,
					'departure_date' =>	$watch->flight->departure_date->toDateString(),
					'findings' =>		$findings,
				];
			}
		}

		return $results;
	}

	// =========================================================================
	// Callbacks for neighbouring days' flights are ignored. FlightAware
	// sometimes replaces a flight's fa_flight_id part way, so each id is
	// audited separately and only the latest is expected to finish.
	private function auditWatch(Watch $watch, Carbon $now): array {
		$groups = $watch->callbacks()
			->orderBy('id')
			->get()
			->filter(fn ($cb) => $cb->matchesFlightDate($watch->flight))
			->groupBy('fa_flight_id')
			->sortBy(fn ($group) => $group->last()->id)
			->values()
		;

		if ($groups->isEmpty()) {
			return $this->audit->audit([], $watch->flight, $watch->activeFrom(), $now);
		}

		$findings = [];

		foreach ($groups as $i => $group) {
			$latest = $i === $groups->count() - 1;

			array_push($findings, ...$this->audit->audit(
				$group, $watch->flight, $watch->activeFrom(), $now, $latest
			));
		}

		return $findings;
	}
}
