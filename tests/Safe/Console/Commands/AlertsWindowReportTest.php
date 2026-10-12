<?php

namespace Tests\Safe\Console\Commands;

use Illuminate\Support\Facades\DB;
use Tests\Safe\TestCase;

// =============================================================================
class AlertsWindowReportTest extends TestCase {
	// =========================================================================
	private function log(int $watchId, string $faId, string $event, int $offset): void {
		DB::table('alert_window_log')->insert([
			'watch_id' =>		$watchId,
			'ident' =>			'UA2389',
			'fa_flight_id' =>	$faId,
			'event_code' =>		$event,
			'watched_date' =>	'2026-10-11',
			'scheduled_out' =>	now()->addHours(10),
			'offset_days' =>	$offset,
			'created_at' =>		now(),
		]);
	}

	// =========================================================================
	public function test_next_days_flight_is_waste_not_a_buffer_catch(): void {
		$this->log(1, 'UAL2389-a', 'filed', 0);
		$this->log(1, 'UAL2389-a', 'in', 0);
		$this->log(1, 'UAL2389-b', 'filed', 1);

		$this->artisan('alerts:window-report')
			->expectsOutputToContain('would be lost without the buffer): 0')
			->expectsOutputToContain('not notified, still billed): 1')
			->expectsOutputToContain('looks safe')
			->assertSuccessful();
	}

	// =========================================================================
	public function test_watched_flight_under_another_date_needs_the_buffer(): void {
		$this->log(1, 'UAL2389-a', 'filed', 0);
		$this->log(1, 'UAL2389-a', 'in', 1);

		$this->artisan('alerts:window-report')
			->expectsOutputToContain('would be lost without the buffer): 1')
			->expectsOutputToContain('depended on the buffer')
			->assertSuccessful();
	}

	// =========================================================================
	public function test_watch_without_any_callback_for_its_date_is_flagged(): void {
		$this->log(2, 'UAL2389-b', 'filed', 1);

		$this->artisan('alerts:window-report')
			->expectsOutputToContain('none for the watched date (date mismatch?): 1')
			->assertSuccessful();
	}
}
