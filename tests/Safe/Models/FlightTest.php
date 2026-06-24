<?php

namespace Tests\Safe\Models;

use Tests\Safe\TestCase;

// =============================================================================
class FlightTest extends TestCase {
	// =========================================================================
	public function test_saving_computes_alert_window_from_departure_date(): void {
		$flight = $this->makeFlight(['departure_date' => '2026-07-01']);

		// alert_start/alert_end are cast as 'date', so only the date portion survives.
		$this->assertSame('2026-06-30', $flight->alert_start->toDateString());
		$this->assertSame('2026-07-03', $flight->alert_end->toDateString());
	}

	// =========================================================================
	public function test_saving_computes_duration_when_departure_and_arrival_set(): void {
		$flight = $this->makeFlight([
			'departure_date' => '2026-07-01',
			'departure_dt' => '2026-07-01 09:00:00',
			'arrival_dt' => '2026-07-01 17:30:00',
		]);

		$this->assertSame(510, $flight->duration);
	}

	// =========================================================================
	public function test_duration_is_not_set_without_both_departure_and_arrival_dt(): void {
		$flight = $this->makeFlight(['departure_date' => '2026-07-01']);

		$this->assertNull($flight->duration);
	}
}
