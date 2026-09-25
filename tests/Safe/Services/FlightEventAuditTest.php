<?php

namespace Tests\Safe\Services;

use App\Models\Flight;
use App\Models\WatchCallback;
use App\Services\FlightEventAudit;
use Carbon\Carbon;
use Tests\Safe\TestCase;

// =============================================================================
// Scheduled out 12:00Z, off 12:20, on 17:40, in 18:00 on 2026-07-01. Unless a
// test says otherwise the alert was created the day before.
class FlightEventAuditTest extends TestCase {
	private const ACTUALS = [
		'actual_out' =>	'2026-07-01T12:05:00Z',
		'actual_off' =>	'2026-07-01T12:20:00Z',
		'actual_on' =>	'2026-07-01T17:40:00Z',
		'actual_in' =>	'2026-07-01T18:00:00Z',
	];

	private const PROGRESS = [
		'filed' =>		[],
		'out' =>		['actual_out'],
		'departure' =>	['actual_out', 'actual_off'],
		'hold_start' =>	['actual_out', 'actual_off'],
		'hold_end' =>	['actual_out', 'actual_off'],
		'diverted' =>	['actual_out', 'actual_off'],
		'arrival' =>	['actual_out', 'actual_off', 'actual_on'],
		'in' =>			['actual_out', 'actual_off', 'actual_on', 'actual_in'],
		'cancelled' =>	[],
		'change' =>		[],
		'minutes_out' =>	[],
		'gate_change' =>	[],
	];

	private Flight $flight;
	private int $nextId = 1;

	// =========================================================================
	protected function setUp(): void {
		parent::setUp();

		$this->flight = $this->makeFlight([
			'departure_date' =>	'2026-07-01',
			'departure_dt' =>	'2026-07-01 12:00:00',
			'arrival_dt' =>		'2026-07-01 18:00:00',
		]);
	}

	// =========================================================================
	// An unsaved callback whose payload has the actual times a real flight
	// would have reached by this event.
	private function cb(string $code, array $overrides = []): WatchCallback {
		$fields = [
			'event_code' =>		$code,
			'fa_flight_id' =>	'UAL100-1',
			'destination' =>	'KJFK',
			'scheduled_out' =>	'2026-07-01T12:00:00Z',
			'scheduled_off' =>	'2026-07-01T12:15:00Z',
			'scheduled_on' =>	'2026-07-01T17:45:00Z',
			'scheduled_in' =>	'2026-07-01T18:00:00Z',
		];

		foreach (self::PROGRESS[$code] as $field) {
			$fields[$field] = self::ACTUALS[$field];
		}

		$cb = new WatchCallback();
		$cb->forceFill(array_merge($fields, $overrides));
		$cb->id = $this->nextId++;

		return $cb;
	}

	// =========================================================================
	private function audit(
		array $callbacks,
		string $activeFrom = '2026-06-30T12:00:00Z',
		string $now = '2026-07-01T19:00:00Z',
	): array {
		return (new FlightEventAudit(config('health.audit')))->audit(
			$callbacks, $this->flight, Carbon::parse($activeFrom), Carbon::parse($now)
		);
	}

	// =========================================================================
	private function codes(array $findings): array {
		return array_map(fn ($f) => "{$f['severity']}:{$f['code']}", $findings);
	}

	// =========================================================================
	public function test_complete_flight_has_no_findings(): void {
		$this->assertSame([], $this->audit(array_map(fn ($c) => $this->cb($c),
			['filed', 'out', 'departure', 'hold_start', 'hold_end', 'arrival', 'in', 'change']
		)));
	}

	// =========================================================================
	public function test_watch_added_mid_flight_may_miss_earlier_events(): void {
		$findings = $this->audit(
			[$this->cb('arrival'), $this->cb('in')],
			activeFrom: '2026-07-01T13:00:00Z',
		);

		$this->assertSame([], $findings);
	}

	// =========================================================================
	public function test_watch_added_mid_hold_may_miss_hold_start(): void {
		$findings = $this->audit(
			[$this->cb('hold_end'), $this->cb('arrival'), $this->cb('in')],
			activeFrom: '2026-07-01T13:00:00Z',
		);

		$this->assertSame([], $findings);
	}

	// =========================================================================
	public function test_missed_event_after_the_watch_was_active_is_an_error(): void {
		$findings = $this->audit([$this->cb('filed'), $this->cb('out'), $this->cb('arrival'), $this->cb('in')]);

		$this->assertSame(['error:missed_event'], $this->codes($findings));
		$this->assertSame(3, $findings[0]['callback_id']);
	}

	// =========================================================================
	public function test_missing_filed_is_a_warning(): void {
		$findings = $this->audit([$this->cb('out'), $this->cb('departure'), $this->cb('arrival'), $this->cb('in')]);

		$this->assertSame(['warning:missed_event'], $this->codes($findings));
	}

	// =========================================================================
	public function test_missing_gate_event_without_a_time_is_a_warning(): void {
		$findings = $this->audit([
			$this->cb('filed'),
			$this->cb('departure', ['actual_out' => null]),
			$this->cb('arrival', ['actual_out' => null]),
			$this->cb('in', ['actual_out' => null]),
		]);

		$this->assertSame(['warning:missed_event'], $this->codes($findings));
	}

	// =========================================================================
	public function test_event_going_backwards_is_an_error(): void {
		$findings = $this->audit([
			$this->cb('filed'),
			$this->cb('departure', ['actual_out' => null]),
			$this->cb('out', ['actual_out' => '2026-07-01T12:30:00Z', 'actual_off' => '2026-07-01T12:20:00Z']),
			$this->cb('arrival'), $this->cb('in'),
		]);

		$this->assertSame(['warning:missed_event', 'error:out_of_order'], $this->codes($findings));
	}

	// =========================================================================
	// Seen on LH400: departure with no actual_out, then out 38s later once
	// FlightAware had the gate time.
	public function test_late_milestone_with_an_earlier_time_fills_the_gap(): void {
		$findings = $this->audit([
			$this->cb('filed'),
			$this->cb('departure', ['actual_out' => null]),
			$this->cb('out'),
			$this->cb('arrival'), $this->cb('in'),
			$this->cb('arrival'),
		]);

		$this->assertSame(['warning:duplicate'], $this->codes($findings));
	}

	// =========================================================================
	public function test_late_milestone_after_in_fills_the_gap(): void {
		$findings = $this->audit([
			$this->cb('filed'), $this->cb('out'), $this->cb('departure'),
			$this->cb('in'), $this->cb('arrival'),
		]);

		$this->assertSame([], $findings);
	}

	// =========================================================================
	public function test_repeated_milestone_out_of_order_is_a_duplicate(): void {
		$findings = $this->audit([
			$this->cb('filed'), $this->cb('out'), $this->cb('departure'), $this->cb('out'),
			$this->cb('arrival'), $this->cb('in'),
		]);

		$this->assertSame(['warning:duplicate'], $this->codes($findings));
	}

	// =========================================================================
	public function test_duplicate_event_is_a_warning(): void {
		$findings = $this->audit([
			$this->cb('filed'), $this->cb('out'), $this->cb('out'), $this->cb('departure'),
			$this->cb('arrival'), $this->cb('in'),
		]);

		$this->assertSame(['warning:duplicate'], $this->codes($findings));
	}

	// =========================================================================
	public function test_cancelled_at_any_point_is_fine(): void {
		$this->assertSame([], $this->audit([$this->cb('cancelled')]));
		$this->assertSame([], $this->audit([$this->cb('filed'), $this->cb('out'), $this->cb('cancelled')]));
	}

	// =========================================================================
	public function test_anything_after_cancelled_is_an_error(): void {
		$findings = $this->audit([$this->cb('filed'), $this->cb('cancelled'), $this->cb('change')]);

		$this->assertSame(['error:event_after_cancelled'], $this->codes($findings));
	}

	// =========================================================================
	public function test_only_change_may_follow_in(): void {
		$findings = $this->audit([
			$this->cb('filed'), $this->cb('out'), $this->cb('departure'), $this->cb('arrival'),
			$this->cb('in'), $this->cb('change'), $this->cb('hold_start'),
		]);

		$this->assertSame(['error:event_after_in'], $this->codes($findings));
	}

	// =========================================================================
	public function test_hold_must_end_before_arrival(): void {
		$findings = $this->audit([
			$this->cb('filed'), $this->cb('out'), $this->cb('departure'), $this->cb('hold_start'),
			$this->cb('arrival'), $this->cb('in'),
		]);

		$this->assertSame(['error:hold_not_ended'], $this->codes($findings));
	}

	// =========================================================================
	public function test_holds_must_alternate(): void {
		$findings = $this->audit([
			$this->cb('filed'), $this->cb('out'), $this->cb('departure'),
			$this->cb('hold_end'), $this->cb('hold_start'), $this->cb('hold_start'), $this->cb('hold_end'),
			$this->cb('arrival'), $this->cb('in'),
		]);

		$this->assertSame(['error:hold_sequence', 'error:hold_sequence'], $this->codes($findings));
	}

	// =========================================================================
	public function test_airborne_event_before_departure_is_a_missed_departure(): void {
		$findings = $this->audit([
			$this->cb('filed'), $this->cb('out'), $this->cb('hold_start'), $this->cb('hold_end'),
			$this->cb('arrival'), $this->cb('in'),
		]);

		$this->assertSame(['error:missed_event'], $this->codes($findings));
	}

	// =========================================================================
	public function test_airborne_event_after_arrival_is_an_error(): void {
		$findings = $this->audit([
			$this->cb('filed'), $this->cb('out'), $this->cb('departure'), $this->cb('arrival'),
			$this->cb('diverted'), $this->cb('in'),
		]);

		$this->assertSame(['error:out_of_order'], $this->codes($findings));
	}

	// =========================================================================
	public function test_diverted_flight_is_finished_by_arrival_elsewhere(): void {
		$findings = $this->audit(
			[
				$this->cb('filed'), $this->cb('out'), $this->cb('departure'), $this->cb('diverted'),
				$this->cb('arrival', ['destination' => 'KBOS']),
			],
			now: '2026-07-02T12:00:00Z',
		);

		$this->assertSame([], $findings);
	}

	// =========================================================================
	public function test_diverted_arrival_at_the_original_destination_is_a_warning(): void {
		$findings = $this->audit([
			$this->cb('filed'), $this->cb('out'), $this->cb('departure'), $this->cb('diverted'),
			$this->cb('arrival'),
		]);

		$this->assertSame(['warning:diverted_same_destination'], $this->codes($findings));
	}

	// =========================================================================
	public function test_actual_times_out_of_order_is_an_error(): void {
		$findings = $this->audit([
			$this->cb('filed'), $this->cb('out'), $this->cb('departure'), $this->cb('arrival'),
			$this->cb('in', ['actual_on' => '2026-07-01T11:00:00Z']),
		]);

		$this->assertSame(['error:times_out_of_order'], $this->codes($findings));
	}

	// =========================================================================
	public function test_airborne_too_long_is_an_error(): void {
		$callbacks = [$this->cb('filed'), $this->cb('out'), $this->cb('departure')];

		$this->assertSame([], $this->audit($callbacks, now: '2026-07-01T20:00:00Z'));
		$this->assertSame(['error:stale'], $this->codes($this->audit($callbacks, now: '2026-07-01T21:00:00Z')));
	}

	// =========================================================================
	public function test_stale_uses_the_latest_estimate(): void {
		$callbacks = [
			$this->cb('filed'), $this->cb('out'),
			$this->cb('departure', ['estimated_on' => '2026-07-01T20:00:00Z']),
		];

		$this->assertSame([], $this->audit($callbacks, now: '2026-07-01T22:00:00Z'));
	}

	// =========================================================================
	public function test_left_gate_but_never_took_off_is_an_error(): void {
		$findings = $this->audit([$this->cb('filed'), $this->cb('out')], now: '2026-07-01T15:00:00Z');

		$this->assertSame(['error:stale'], $this->codes($findings));
	}

	// =========================================================================
	public function test_only_filed_is_a_warning(): void {
		$findings = $this->audit([$this->cb('filed')], now: '2026-07-01T19:00:00Z');

		$this->assertSame(['warning:stale'], $this->codes($findings));
	}

	// =========================================================================
	public function test_landed_without_gate_arrival_is_a_warning(): void {
		$callbacks = [$this->cb('filed'), $this->cb('out'), $this->cb('departure'), $this->cb('arrival')];

		$this->assertSame([], $this->audit($callbacks, now: '2026-07-01T19:00:00Z'));
		$this->assertSame(['warning:stale'], $this->codes($this->audit($callbacks, now: '2026-07-01T20:00:00Z')));
	}

	// =========================================================================
	public function test_no_callbacks_is_an_error(): void {
		$this->assertSame([], $this->audit([], now: '2026-07-01T17:00:00Z'));

		$findings = $this->audit([], now: '2026-07-01T19:00:00Z');

		$this->assertSame(['error:stale'], $this->codes($findings));
		$this->assertNull($findings[0]['callback_id']);
	}

	// =========================================================================
	public function test_no_callbacks_is_fine_if_the_watch_started_after_the_flight(): void {
		$this->assertSame([], $this->audit([], activeFrom: '2026-07-01T18:30:00Z', now: '2026-07-01T19:00:00Z'));
	}

	// =========================================================================
	public function test_unknown_event_is_a_warning(): void {
		$findings = $this->audit([
			$this->cb('filed'), $this->cb('out'), $this->cb('minutes_out'), $this->cb('departure'),
			$this->cb('arrival'), $this->cb('in'),
		]);

		$this->assertSame(['warning:unknown_event'], $this->codes($findings));
		$this->assertSame("Unknown event 'minutes_out'; no notification was sent", $findings[0]['message']);
	}

	// =========================================================================
	// gate_change has a notification class but FlightAware v4 doesn't send it.
	public function test_unknown_event_with_a_notification_says_so(): void {
		$findings = $this->audit([
			$this->cb('filed'), $this->cb('out'), $this->cb('gate_change'), $this->cb('departure'),
			$this->cb('arrival'), $this->cb('in'),
		]);

		$this->assertSame("Unknown event 'gate_change'", $findings[0]['message']);
	}

	// =========================================================================
	// Every event the audit sequences has a notification.
	public function test_every_known_event_is_handled(): void {
		foreach (['filed', 'out', 'departure', 'arrival', 'in', 'cancelled', 'change',
				'diverted', 'hold_start', 'hold_end'] as $code) {
			$this->assertTrue(WatchCallback::handles($code), $code);
		}

		$this->assertFalse(WatchCallback::handles('minutes_out'));
		$this->assertFalse(WatchCallback::handles(null));
	}

	// =========================================================================
	public function test_stale_check_can_be_skipped(): void {
		$findings = (new FlightEventAudit(config('health.audit')))->audit(
			[$this->cb('filed')], $this->flight,
			Carbon::parse('2026-06-30T12:00:00Z'), Carbon::parse('2026-07-02T12:00:00Z'),
			checkStale: false,
		);

		$this->assertSame([], $findings);
	}
}
