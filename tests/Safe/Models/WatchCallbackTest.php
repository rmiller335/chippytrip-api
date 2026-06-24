<?php

namespace Tests\Safe\Models;

use App\Models\WatchCallback;
use Tests\Safe\TestCase;

// =============================================================================
class WatchCallbackTest extends TestCase {
	// =========================================================================
	private function payload(array $overrides = []): array {
		return array_merge([
			'alert_id' => 'SUB123',
			'event_code' => 'departure',
			'summary' => 'UA100 departed',
			'flight' => [
				'fa_flight_id' => 'FA123',
				'ident' => 'UA100',
				'origin' => [ 'code' => 'KSFO', 'code_icao' => 'KSFO' ],
				'destination' => [ 'code' => 'KJFK', 'code_icao' => 'KJFK' ],
				'scheduled_out' => '2026-07-01T09:00:00Z',
				'actual_out' => '2026-07-01T09:05:00Z',
				'actual_off' => null,
				'actual_in' => '2026-07-01T17:30:00Z',
				'actual_on' => null,
			],
		], $overrides);
	}

	// =========================================================================
	public function test_from_api_payload_maps_envelope_and_flight_fields(): void {
		$wc = WatchCallback::fromApiPayload($this->payload(), '127.0.0.1');

		$this->assertSame('SUB123', $wc->alert_id);
		$this->assertSame('departure', $wc->event_code);
		$this->assertSame('FA123', $wc->fa_flight_id);
		$this->assertSame('KSFO', $wc->origin);
		$this->assertSame('KJFK', $wc->destination);
		$this->assertSame('127.0.0.1', $wc->source_ip);
	}

	// =========================================================================
	public function test_departed_at_falls_back_to_actual_out(): void {
		$wc = WatchCallback::fromApiPayload($this->payload());

		$this->assertTrue($wc->departed_at->eq($wc->actual_out));
	}

	// =========================================================================
	public function test_arrived_at_falls_back_to_actual_in(): void {
		$wc = WatchCallback::fromApiPayload($this->payload());

		$this->assertTrue($wc->arrived_at->eq($wc->actual_in));
	}

	// =========================================================================
	public function test_block_minutes_is_null_without_both_timestamps(): void {
		$wc = WatchCallback::fromApiPayload($this->payload([
			'flight' => array_merge($this->payload()['flight'], ['actual_in' => null]),
		]));

		$this->assertNull($wc->block_minutes);
	}

	// =========================================================================
	public function test_block_minutes_computed_when_both_timestamps_present(): void {
		$wc = WatchCallback::fromApiPayload($this->payload([
			'flight' => array_merge($this->payload()['flight'], [
				'actual_out' => '2026-07-01T09:00:00Z',
				'actual_in' => '2026-07-01T09:30:00Z',
			]),
		]));

		$this->assertSame(30, $wc->block_minutes);
	}

	// =========================================================================
	public function test_scope_cancelled_and_diverted(): void {
		WatchCallback::fromApiPayload($this->payload(['flight' => array_merge($this->payload()['flight'], ['cancelled' => true])]))->save();
		WatchCallback::fromApiPayload($this->payload(['flight' => array_merge($this->payload()['flight'], ['diverted' => true])]))->save();
		WatchCallback::fromApiPayload($this->payload())->save();

		$this->assertSame(1, WatchCallback::cancelled()->count());
		$this->assertSame(1, WatchCallback::diverted()->count());
	}

	// =========================================================================
	public function test_scope_for_event_and_for_ident(): void {
		WatchCallback::fromApiPayload($this->payload())->save();
		WatchCallback::fromApiPayload($this->payload(['event_code' => 'arrival']))->save();

		$this->assertSame(1, WatchCallback::forEvent('arrival')->count());
		$this->assertSame(2, WatchCallback::forIdent('UA100')->count());
	}
}
