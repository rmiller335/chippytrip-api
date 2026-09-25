<?php

namespace App\Services;

use App\Models\Flight;
use App\Models\WatchCallback;
use Carbon\Carbon;

// =============================================================================
// Checks one flight's callbacks, in the order they were received, against the
// order FlightAware events should arrive in:
//
//   filed → out → departure → (diverted, hold_start … hold_end) → arrival → in
//
// cancelled can come at any point but nothing may follow it. change events
// are ignored except after cancelled. Milestones that happened before the
// alert existed may be missing (a watch added mid-flight). FlightAware
// sometimes sends a milestone late once it learns the time (an out just after
// departure), which fills the gap as long as its time fits. A flight that
// stops progressing well past its expected times is flagged too, as are
// events we don't recognize or have no notification for.
//
// No database access: callers load and order the callbacks, and pass the
// time the alert became active and "now".
class FlightEventAudit {
	public const ERROR =	'error';
	public const WARNING =	'warning';

	// Milestone events in order, with the payload time each one records.
	private const MILESTONES = [
		'filed' =>		[0, null],
		'out' =>		[1, 'actual_out'],
		'departure' =>	[2, 'actual_off'],
		'arrival' =>	[3, 'actual_on'],
		'in' =>			[4, 'actual_in'],
	];

	// Events that only make sense while airborne.
	private const AIRBORNE = ['diverted', 'hold_start', 'hold_end'];

	private const FILED =		0;
	private const OUT =			1;
	private const DEPARTURE =	2;
	private const ARRIVAL =		3;
	private const IN =			4;

	private array $config;
	private array $findings;

	// Per-run state.
	private int $rank;
	private array $seen;
	private array $missed;
	private ?string $terminal;
	private bool $inHold;
	private bool $holdSeen;
	private bool $diverted;
	private bool $joinedAirborne;
	private Carbon $activeUntil;

	// =========================================================================
	public function __construct(?array $config = null) {
		$this->config = $config ?? config('health.audit');
	}

	// =========================================================================
	// $callbacks: one fa_flight_id's callbacks in the order received.
	// $checkStale: false for an fa_flight_id that FlightAware has replaced,
	// which is expected to stop part way.
	//
	// Returns a list of ['severity', 'code', 'callback_id', 'message'].
	public function audit(
		iterable $callbacks,
		Flight $flight,
		Carbon $activeFrom,
		Carbon $now,
		bool $checkStale = true,
	): array {
		$this->findings =		[];
		$this->rank =			-1;
		$this->seen =			[];
		$this->missed =			[];
		$this->terminal =		null;
		$this->inHold =			false;
		$this->holdSeen =		false;
		$this->diverted =		false;
		$this->joinedAirborne =	false;
		$this->activeUntil =	$activeFrom->copy()->addMinutes($this->config['late_join_grace']);

		$last = null;

		foreach ($callbacks as $cb) {
			$this->check($cb, $flight);
			$last = $cb;
		}

		if (null !== $last) {
			$this->checkTimes($last);
		}

		if ($checkStale) {
			$this->checkStale($last, $flight, $activeFrom, $now);
		}

		return array_values($this->findings);
	}

	// =========================================================================
	private function check(WatchCallback $cb, Flight $flight): void {
		$code = $cb->event_code;
		$known = isset(self::MILESTONES[$code]) || in_array($code, self::AIRBORNE)
			|| in_array($code, ['cancelled', 'change']);

		if (! $known) {
			$this->add(self::WARNING, 'unknown_event', $cb, "Unknown event '{$code}'"
				. (WatchCallback::handles($code) ? '' : '; no notification was sent'));
			return;
		}

		if (! WatchCallback::handles($code)) {
			$this->add(self::WARNING, 'unhandled_event', $cb, "No notification for '{$code}'");
		}

		if ('cancelled' === $this->terminal) {
			$this->add(self::ERROR, 'event_after_cancelled', $cb, "'{$code}' after cancelled");
			return;
		}

		// A milestone after in may be one FlightAware sent late.
		if ('in' === $this->terminal && 'change' !== $code && ! isset(self::MILESTONES[$code])) {
			$this->add(self::ERROR, 'event_after_in', $cb, "'{$code}' after in");
			return;
		}

		if (isset(self::MILESTONES[$code])) {
			$this->checkMilestone($cb, self::MILESTONES[$code][0], $flight);
		}
		elseif (in_array($code, self::AIRBORNE)) {
			$this->checkAirborne($cb);
		}
		elseif ('cancelled' === $code) {
			$this->terminal = 'cancelled';
		}
	}

	// =========================================================================
	private function checkMilestone(WatchCallback $cb, int $rank, Flight $flight): void {
		$code = $cb->event_code;

		if ($rank < $this->rank) {
			$this->checkLate($cb, $rank);
			return;
		}

		if ($rank === $this->rank) {
			$this->add(self::WARNING, 'duplicate', $cb, "Duplicate '{$code}'");
			return;
		}

		$this->checkSkipped($cb, $rank);
		$this->rank = $rank;
		$this->seen[$rank] = true;

		if (self::ARRIVAL === $rank && $this->inHold) {
			$this->add(self::ERROR, 'hold_not_ended', $cb, 'arrival while still in a hold');
			$this->inHold = false;
		}

		if (self::ARRIVAL <= $rank && $this->diverted
				&& null !== $cb->destination
				&& in_array($flight->destination_icao, [$cb->destination, $cb->destination_icao])) {
			$this->add(self::WARNING, 'diverted_same_destination', $cb,
				"'{$code}' after diverted still at {$flight->destination_icao}");
		}

		if (self::IN === $rank) {
			$this->terminal = 'in';
		}
	}

	// =========================================================================
	// A milestone received after a later one. Fine if it was never received
	// and its time comes before the later milestone's; that also clears the
	// finding for having skipped it.
	private function checkLate(WatchCallback $cb, int $rank): void {
		$code = $cb->event_code;

		if (isset($this->seen[$rank])) {
			$this->add(self::WARNING, 'duplicate', $cb, "Duplicate '{$code}'");
			return;
		}

		$at = $this->milestoneTime($cb, $rank);
		$laterAt = $this->milestoneTime($cb, $this->rank);

		if (self::FILED !== $rank && (null === $at || (null !== $laterAt && $at->gt($laterAt)))) {
			$this->add(self::ERROR, 'out_of_order', $cb,
				"'{$code}' after '" . $this->milestoneName($this->rank) . "'");
			return;
		}

		$this->seen[$rank] = true;

		if (isset($this->missed[$rank])) {
			unset($this->findings[$this->missed[$rank]]);
			unset($this->missed[$rank]);
		}
	}

	// =========================================================================
	private function checkAirborne(WatchCallback $cb): void {
		$code = $cb->event_code;

		if (self::DEPARTURE < $this->rank) {
			$this->add(self::ERROR, 'out_of_order', $cb,
				"'{$code}' after '" . $this->milestoneName($this->rank) . "'");
			return;
		}

		// A watch added mid-flight may start with an airborne event, and
		// may have missed a hold's start.
		if (self::DEPARTURE > $this->rank) {
			$this->joinedAirborne = null !== $cb->actual_off
				&& $cb->actual_off->lt($this->activeUntil);

			$this->checkSkipped($cb, self::DEPARTURE + 1);
			$this->rank = self::DEPARTURE;
		}

		switch ($code) {
			case 'hold_start':
				if ($this->inHold) {
					$this->add(self::ERROR, 'hold_sequence', $cb, 'hold_start while already in a hold');
				}
				$this->inHold = true;
				$this->holdSeen = true;
				break;

			case 'hold_end':
				if (! $this->inHold && ($this->holdSeen || ! $this->joinedAirborne)) {
					$this->add(self::ERROR, 'hold_sequence', $cb, 'hold_end without hold_start');
				}
				$this->inHold = false;
				$this->holdSeen = true;
				break;

			case 'diverted':
				if ($this->diverted) {
					$this->add(self::WARNING, 'duplicate', $cb, "Duplicate 'diverted'");
				}
				$this->diverted = true;
				break;
		}
	}

	// =========================================================================
	// Every milestone between the current rank and $rank was never received.
	// That's fine if it happened before the alert existed.
	private function checkSkipped(WatchCallback $cb, int $rank): void {
		for ($skipped = $this->rank + 1; $skipped < $rank; $skipped++) {
			$name = $this->milestoneName($skipped);

			if (self::FILED === $skipped) {
				$departed = $cb->actual_out ?? $cb->actual_off;

				if (null === $departed || $departed->gte($this->activeUntil)) {
					$this->missed[$skipped] = $this->add(self::WARNING, 'missed_event', $cb, "No 'filed' before '{$cb->event_code}'");
				}
				continue;
			}

			$at = $this->milestoneTime($cb, $skipped);

			if (null === $at) {
				// FlightAware often has no gate times, so a missing out or in
				// with no time is a data gap, not a lost callback.
				$gate = in_array($skipped, [self::OUT, self::IN]);

				$this->missed[$skipped] = $this->add($gate ? self::WARNING : self::ERROR, 'missed_event', $cb,
					"No '{$name}' before '{$cb->event_code}', and no time for it");
			}
			elseif ($at->gte($this->activeUntil)) {
				$this->missed[$skipped] = $this->add(self::ERROR, 'missed_event', $cb,
					"No '{$name}' before '{$cb->event_code}', though it happened at {$at->toIso8601String()}");
			}
		}
	}

	// =========================================================================
	// The latest payload's actual times must run out ≤ off ≤ on ≤ in.
	private function checkTimes(WatchCallback $cb): void {
		$prev = null;

		foreach (['actual_out', 'actual_off', 'actual_on', 'actual_in'] as $field) {
			$at = $cb->{$field};

			if (null === $at) {
				continue;
			}

			if (null !== $prev && $at->lt($cb->{$prev})) {
				$this->add(self::ERROR, 'times_out_of_order', $cb,
					"{$field} {$at->toIso8601String()} is before {$prev} {$cb->{$prev}->toIso8601String()}");
			}

			$prev = $field;
		}
	}

	// =========================================================================
	private function checkStale(?WatchCallback $last, Flight $flight, Carbon $activeFrom, Carbon $now): void {
		if (null !== $this->terminal || ($this->diverted && self::ARRIVAL <= $this->rank)) {
			return;
		}

		[$base, $minutes, $severity, $state] = match (true) {
			self::FILED >= $this->rank => [
				$last?->estimated_out ?? $last?->scheduled_out ?? $flight->departure_dt,
				$this->config['filed_stale'],
				self::FILED === $this->rank ? self::WARNING : self::ERROR,
				self::FILED === $this->rank ? 'Only filed' : 'No progress events',
			],
			self::OUT === $this->rank => [
				$last->estimated_off ?? $last->scheduled_off ?? $last->estimated_out ?? $last->scheduled_out,
				$this->config['out_stale'], self::ERROR, 'Left the gate but never took off',
			],
			self::DEPARTURE === $this->rank => [
				$last->estimated_on ?? $last->scheduled_on ?? $last->estimated_in ?? $last->scheduled_in
					?? $flight->arrival_dt,
				$this->config['airborne_stale'], self::ERROR, 'Took off but never landed',
			],
			default => [
				$last->actual_on,
				$this->config['landed_stale'], self::WARNING, 'Landed but never reached the gate',
			],
		};

		if (null === $base) {
			return;
		}

		$deadline = $base->copy()->addMinutes($minutes);

		// No callbacks at all is only a problem if the alert existed while
		// the flight was still to come.
		if (-1 === $this->rank) {
			$end = $flight->arrival_dt ?? $base;

			if ($activeFrom->gte($end)) {
				return;
			}
		}

		if ($now->gt($deadline)) {
			$this->add($severity, 'stale', $last,
				"{$state}; expected by {$base->toIso8601String()}");
		}
	}

	// =========================================================================
	private function milestoneTime(WatchCallback $cb, int $rank): ?Carbon {
		$field = self::MILESTONES[$this->milestoneName($rank)][1];

		return null === $field ? null : $cb->{$field};
	}

	// =========================================================================
	private function milestoneName(int $rank): string {
		return array_search($rank, array_map(fn ($m) => $m[0], self::MILESTONES));
	}

	// =========================================================================
	// Returns the finding's key, so it can be withdrawn.
	private function add(string $severity, string $code, ?WatchCallback $cb, string $message): int {
		$this->findings[] = [
			'severity' =>		$severity,
			'code' =>			$code,
			'callback_id' =>	$cb?->id,
			'message' =>		$message,
		];

		return array_key_last($this->findings);
	}
}
