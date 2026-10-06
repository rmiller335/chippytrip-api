<?php

namespace App\Console\Commands;

use App\Models\Listener;
use App\Models\Watch;
use App\Models\WatchCallback;
use Illuminate\Console\Command;

/**
 * Dumps the canary users' watches, listeners and callbacks as JSON for the
 * chippy-canary verifier. Read-only.
 *
 *   php artisan canary:state --email=canary-alice@example.net --email=...
 *
 * There is one shared watch per flight; users watch it through listener rows.
 * The owner is the first listener (the user whose add created the watch) and
 * subscribers are everyone else listening. The JSON shape at the bottom is
 * the contract.
 */
// =============================================================================
class CanaryState extends Command {
	// Actual-time fields for each event code. Only these say when the event
	// really happened; the model's event_dt falls back to estimated or
	// scheduled times, which would make latency figures meaningless.
	private const ACTUAL_FIELDS = [
		'out' =>		'actual_out',
		'off' =>		'actual_off',
		'on' =>			'actual_on',
		'in' =>			'actual_in',
		'departure' =>	'actual_out',
		'arrival' =>	'actual_in',
		'diverted' =>	'actual_on',
	];

	protected $signature = 'canary:state
		{--email=* : User email addresses to include}
		{--days=10 : Only watches created in the last N days}';

	protected $description = 'JSON dump of canary users\' watches for the end-to-end canary';

	// =========================================================================
	public function handle(): int {
		$emails = array_map('strtolower', $this->option('email'));
		$since = now()->subDays((int) $this->option('days'));

		$watches = Watch::query()
			->whereHas('listeners.user', fn ($q) => $q->whereIn('email', $emails))
			->where('created_at', '>=', $since)
			->with([
				'flight.airline',
				'listeners' => fn ($q) => $q->orderBy('id')->with('user'),
				'callbacks' => fn ($q) => $q->orderBy('created_at')->orderBy('id'),
			])
			->orderBy('id')
			->get();

		$out = $watches->map(function (Watch $w) {
			$flight = $w->flight;
			$iata = $flight?->airline?->iata;

			$emails = $w->listeners
				->map(fn (Listener $l) => strtolower((string) $l->user?->email));

			return [
				'id' => $w->id,
				'owner_email' => $emails->first(),
				'ident' => $flight ? $flight->airline_icao . $flight->flight_no : null,
				'ident_iata' => $flight && $iata ? $iata . $flight->flight_no : null,
				'flight_date' => $flight?->departure_date?->format('Y-m-d'),
				'subscription_id' => $w->subscription_id,
				'deleted' => false,		// watches are hard-deleted
				'subscribers' => $emails->slice(1)->values(),
				'callbacks' => $w->callbacks
					->map(fn (WatchCallback $c) => [
						'notification_id' => $c->notification_id,
						'event_code' => $c->event_code,
						'created_at' => $c->created_at->toIso8601String(),
						'event_at' => $this->actualEventTime($c),
					])->values(),
			];
		})->values();

		$this->line(json_encode([
			'generated_at' => now()->toIso8601String(),
			'watches' => $out,
		], JSON_UNESCAPED_SLASHES));

		return self::SUCCESS;
	}

	// =========================================================================
	// When FlightAware says the event actually happened, or null when the
	// payload has no actual time for this kind of event (filed, cancelled,
	// holds, or a departure reported before actual_out is known).
	private function actualEventTime(WatchCallback $c): ?string {
		$field = self::ACTUAL_FIELDS[strtolower((string) $c->event_code)] ?? null;

		if ($field === null || $c->{$field} === null) {
			return null;
		}

		return $c->{$field}->toIso8601String();
	}
}
