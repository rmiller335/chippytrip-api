<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Shows whether the AeroAPI alert buffer (flightaware.alert_buffer_days) is
 * earning its keep, from alert_window_log (one row per callback).
 *
 * The buffer is needed only if a callback for the watched departure arrives
 * dated on a different day than the one we watched. That shows up either as
 * the same fa_flight_id seen at offset 0 and at another offset, or as a watch
 * whose callbacks never land on offset 0. Everything else outside offset 0 is
 * another day's flight with the same number: stored, not notified, and
 * charged by FlightAware as a query.
 *
 *   php artisan alerts:window-report --days=30
 */
#[Signature('alerts:window-report {--days=30 : How far back to look}')]
#[Description('Whether the AeroAPI alert date buffer ever caught a callback for a watched flight')]
class AlertsWindowReport extends Command {
	// =========================================================================
	public function handle(): int {
		$days = max(1, (int) $this->option('days'));
		$since = now()->subDays($days);

		$rows = DB::table('alert_window_log')
			->where('created_at', '>=', $since)
			->orderBy('id')
			->get();

		$this->line(sprintf('Callbacks since %s (%d days), buffer now %d day(s): %d',
			$since->toDateString(), $days, (int) config('flightaware.alert_buffer_days'), $rows->count()));

		if ($rows->isEmpty()) {
			$this->line('Nothing logged yet.');
			return self::SUCCESS;
		}

		$this->offsets($rows);
		$needed = $this->bufferCatches($rows);
		$this->filedLead($rows);

		$this->newLine();
		$wasted = $rows->filter(fn ($r) => null !== $r->offset_days && 0 !== (int) $r->offset_days)->count();
		$this->line("Callbacks for other departures (not notified, still billed): {$wasted}");

		if (0 === $needed) {
			$this->info('Verdict: the buffer caught nothing for a watched departure. '
				. 'FLIGHTAWARE_ALERT_BUFFER_DAYS=0 looks safe.');
		}
		else {
			$this->warn("Verdict: {$needed} case(s) above depended on the buffer. Look at them before removing it.");
		}

		return self::SUCCESS;
	}

	// =========================================================================
	private function offsets(Collection $rows): void {
		$this->newLine();
		$this->line('By offset from the watched date (callback date - watched date):');
		$this->table(['offset', 'callbacks', 'watches'],
			$rows->groupBy(fn ($r) => $r->offset_days ?? 'n/a')
				->sortKeys()
				->map(fn ($g, $k) => [is_numeric($k) && $k > 0 ? "+{$k}" : $k, $g->count(),
					$g->pluck('watch_id')->unique()->count()])
				->values()->all());
	}

	// =========================================================================
	// Rows that only arrived because of the buffer yet belong to the watched
	// departure. Returns how many cases were found.
	private function bufferCatches(Collection $rows): int {
		$shifted = [];
		$noMatch = [];

		foreach ($rows->groupBy('watch_id') as $watchId => $g) {
			$watchedIds = $g->where('offset_days', 0)->pluck('fa_flight_id')->filter()->unique();

			if ($watchedIds->isEmpty()) {
				if ($g->contains(fn ($r) => null !== $r->offset_days)) {
					$first = $g->first();
					$noMatch[] = [$watchId, $first->ident, $first->watched_date,
						$g->pluck('offset_days')->unique()->sort()->implode(','), $g->count()];
				}
				continue;
			}

			// The watched flight (same fa_flight_id) seen under another date.
			foreach ($g as $r) {
				if (null !== $r->offset_days && 0 !== (int) $r->offset_days
					&& $watchedIds->contains($r->fa_flight_id)) {
					$shifted[] = [$watchId, $r->ident, $r->watched_date, $r->event_code,
						$r->offset_days, $r->scheduled_out];
				}
			}
		}

		$this->newLine();
		$this->line('Watched flight seen under a different date (would be lost without the buffer): '
			. count($shifted));
		if ($shifted) {
			$this->table(['watch', 'flight', 'watched', 'event', 'offset', 'scheduled_out'], $shifted);
		}

		$this->line('Watches with callbacks but none for the watched date (date mismatch?): ' . count($noMatch));
		if ($noMatch) {
			$this->table(['watch', 'flight', 'watched', 'offsets seen', 'callbacks'], $noMatch);
		}

		return count($shifted) + count($noMatch);
	}

	// =========================================================================
	// How early "filed" arrives for watched departures: the case a tight window
	// was feared to miss (a plan filed the night before an early flight).
	private function filedLead(Collection $rows): void {
		$filed = $rows->where('event_code', 'filed')->where('offset_days', 0)
			->filter(fn ($r) => null !== $r->scheduled_out);

		$watched = $rows->where('offset_days', 0)->pluck('watch_id')->unique();
		$withFiled = $filed->pluck('watch_id')->unique();

		$leads = $filed->map(fn ($r) => Carbon::parse($r->created_at, 'UTC')
			->diffInMinutes(Carbon::parse($r->scheduled_out, 'UTC'), false) / 60)
			->sort()->values();

		$this->newLine();
		$this->line(sprintf('Watched departures: %d, with a filed callback: %d',
			$watched->count(), $withFiled->count()));

		if ($leads->isNotEmpty()) {
			$this->line(sprintf('Filed arrives before scheduled departure: earliest %.1fh, median %.1fh, latest %.1fh',
				$leads->last(), $leads->median(), $leads->first()));
		}
	}
}
