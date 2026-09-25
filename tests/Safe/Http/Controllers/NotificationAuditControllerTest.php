<?php

namespace Tests\Safe\Http\Controllers;

use App\Models\Flight;
use App\Models\Watch;
use App\Models\WatchCallback;
use App\Services\NotificationAuditSvc;
use Carbon\Carbon;
use Tests\Safe\TestCase;

// =============================================================================
// GET /health/notifications, notifications:audit and NotificationAuditSvc,
// against the database. The sequence rules are in FlightEventAuditTest.
class NotificationAuditControllerTest extends TestCase {
	private Flight $flight;
	private Watch $watch;

	// =========================================================================
	protected function setUp(): void {
		parent::setUp();

		config(['health.token' => 'health-secret']);
		Carbon::setTestNow('2026-07-01 19:00:00');

		$this->flight = $this->makeFlight([
			'departure_date' =>	'2026-07-01',
			'departure_dt' =>	'2026-07-01 12:00:00',
			'arrival_dt' =>		'2026-07-01 18:00:00',
		]);

		$this->watch = Watch::create(['flight_id' => $this->flight->id, 'enabled' => true]);
		$this->watch->forceFill(['created_at' => '2026-06-30 12:00:00'])->save();
	}

	// =========================================================================
	protected function tearDown(): void {
		Carbon::setTestNow();

		parent::tearDown();
	}

	// =========================================================================
	private function addCallback(string $code, array $overrides = []): WatchCallback {
		return WatchCallback::forceCreate(array_merge([
			'alert_id' =>		1,
			'watch_id' =>		$this->watch->id,
			'event_code' =>		$code,
			'fa_flight_id' =>	'UAL100-1',
			'ident' =>			'UAL100',
			'raw_payload' =>	[],
			'scheduled_out' =>	'2026-07-01 12:00:00',
			'scheduled_on' =>	'2026-07-01 17:45:00',
			'actual_out' =>		in_array($code, ['out', 'departure', 'arrival', 'in']) ? '2026-07-01 12:05:00' : null,
			'actual_off' =>		in_array($code, ['departure', 'arrival', 'in']) ? '2026-07-01 12:20:00' : null,
			'actual_on' =>		in_array($code, ['arrival', 'in']) ? '2026-07-01 17:40:00' : null,
			'actual_in' =>		'in' === $code ? '2026-07-01 18:00:00' : null,
		], $overrides));
	}

	// =========================================================================
	private function completeFlight(): void {
		foreach (['filed', 'out', 'departure', 'arrival', 'in'] as $code) {
			$this->addCallback($code);
		}
	}

	// =========================================================================
	public function test_returns_404_without_the_token(): void {
		$this->getJson('/health/notifications')->assertStatus(404);
		$this->getJson('/health/notifications', ['X-Health-Token' => 'wrong'])->assertStatus(404);
	}

	// =========================================================================
	public function test_ok_when_every_flight_is_in_order(): void {
		$this->completeFlight();

		$this->getJson('/health/notifications', ['X-Health-Token' => 'health-secret'])
			->assertStatus(200)
			->assertJson(['status' => 'ok', 'errors' => 0, 'warnings' => 0, 'flights' => []]);
	}

	// =========================================================================
	public function test_warning_is_still_200(): void {
		foreach (['out', 'departure', 'arrival', 'in'] as $code) {
			$this->addCallback($code);
		}

		$this->getJson('/health/notifications', ['X-Health-Token' => 'health-secret'])
			->assertStatus(200)
			->assertJson(['status' => 'warning', 'errors' => 0, 'warnings' => 1]);
	}

	// =========================================================================
	public function test_error_is_503_with_details(): void {
		foreach (['filed', 'out', 'arrival', 'in'] as $code) {
			$this->addCallback($code);
		}

		$this->getJson('/health/notifications', ['X-Health-Token' => 'health-secret'])
			->assertStatus(503)
			->assertJson([
				'status' =>		'critical',
				'errors' =>		1,
				'flights' =>	[[
					'watch_id' =>		$this->watch->id,
					'flight' =>			'UA100',
					'departure_date' =>	'2026-07-01',
					'findings' =>		[['severity' => 'error', 'code' => 'missed_event']],
				]],
			]);
	}

	// =========================================================================
	public function test_callbacks_for_other_days_are_ignored(): void {
		$this->completeFlight();
		$this->addCallback('filed', ['fa_flight_id' => 'UAL100-2', 'scheduled_out' => '2026-07-02 12:00:00']);

		$this->assertSame([], app(NotificationAuditSvc::class)->run());
	}

	// =========================================================================
	public function test_replaced_flight_id_is_not_expected_to_finish(): void {
		$this->addCallback('filed');
		$this->completeFlight();
		$this->watch->callbacks()->where('id', '>', $this->watch->callbacks()->min('id'))
			->update(['fa_flight_id' => 'UAL100-2']);

		$this->assertSame([], app(NotificationAuditSvc::class)->run());
	}

	// =========================================================================
	public function test_enabled_at_marks_when_events_could_be_seen(): void {
		$this->addCallback('arrival');
		$this->addCallback('in');

		$this->assertCount(1, app(NotificationAuditSvc::class)->run());

		$this->watch->enable('sub-1', 'secret');
		$this->watch->enabled_at = '2026-07-01 13:00:00';
		$this->watch->save();

		$this->assertSame([], app(NotificationAuditSvc::class)->run());
	}

	// =========================================================================
	public function test_enable_sets_enabled_at(): void {
		$this->watch->enable('sub-1', 'secret');

		$this->assertTrue($this->watch->enabled_at->eq(Carbon::now()));
	}

	// =========================================================================
	public function test_watch_never_enabled_is_skipped(): void {
		$this->watch->update(['enabled' => false]);

		$this->assertSame([], app(NotificationAuditSvc::class)->run());
	}

	// =========================================================================
	public function test_flights_outside_the_lookback_are_skipped(): void {
		Carbon::setTestNow('2026-07-05 12:00:00');

		$this->assertSame([], app(NotificationAuditSvc::class)->run());
		$this->assertCount(1, app(NotificationAuditSvc::class)->run(lookbackHours: 24 * 5));
	}

	// =========================================================================
	public function test_command_exits_1_on_errors(): void {
		$this->artisan('notifications:audit')
			->expectsOutputToContain('No progress events')
			->assertExitCode(1);
	}

	// =========================================================================
	public function test_command_exits_0_when_clean(): void {
		$this->completeFlight();

		$this->artisan('notifications:audit')
			->expectsOutput('No problems found.')
			->assertExitCode(0);
	}
}
