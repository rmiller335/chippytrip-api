<?php

namespace Tests\Safe\Services;

use App\Http\Resources\Sync\WatchCallbackSyncResource;
use App\Models\User;
use App\Models\Watch;
use App\Models\WatchCallback;
use App\Notifications\Departure;
use Tests\Safe\TestCase;

// =============================================================================
// SFO is America/Los_Angeles (UTC-7 in July), JFK America/New_York (UTC-4).
class CallbackTextTest extends TestCase {
	// =========================================================================
	protected function setUp(): void {
		parent::setUp();

		$this->makeFlight(); // Seeds the SFO and JFK airports.
	}

	// =========================================================================
	// An AeroAPI alert payload: airports as code strings plus flat fields.
	private function makeCallback(string $event, array $flight = [], bool $save = true): WatchCallback {
		$cb = WatchCallback::fromApiPayload([
			'alert_id' => '1234567',
			'event_code' => $event,
			'summary' => 'UAL100 FlightAware summary',
			'short_description' => 'UAL100 FlightAware short description',
			'long_description' => "UAL100 FlightAware long description\n\nwith lines",
			'flight' => array_merge([
				'fa_flight_id' => 'UAL100-1',
				'ident' => 'UAL100',
				'ident_icao' => 'UAL100',
				'ident_iata' => 'UA100',
				'origin' => 'KSFO',
				'origin_icao' => 'KSFO',
				'origin_iata' => 'SFO',
				'destination' => 'KJFK',
				'destination_icao' => 'KJFK',
				'destination_iata' => 'JFK',
				'scheduled_out' => '2026-07-01T16:00:00Z',	// 9:00 AM PDT
				'scheduled_off' => '2026-07-01T16:15:00Z',
				'scheduled_on' => '2026-07-02T00:20:00Z',
				'scheduled_in' => '2026-07-02T00:30:00Z',	// 8:30 PM EDT
			], $flight),
		]);

		if ($save) {
			$cb->save();
		}

		return $cb;
	}

	// =========================================================================
	public function test_filed_on_time(): void {
		$cb = $this->makeCallback('filed', [
			'estimated_out' => '2026-07-01T16:00:00Z',
			'terminal_origin' => '3',
			'gate_origin' => 'F12',
		]);

		$this->assertSame('UA100 is on time', $cb->title);
		$this->assertSame('Departs SFO 9:00 AM from Terminal 3, Gate F12. Arrives JFK 8:30 PM.', $cb->body);
	}

	// =========================================================================
	public function test_filed_running_late(): void {
		$cb = $this->makeCallback('filed', ['estimated_out' => '2026-07-01T17:25:00Z']);

		$this->assertSame('UA100 is running 1 hr 25 min late', $cb->title);
	}

	// =========================================================================
	public function test_left_gate(): void {
		$cb = $this->makeCallback('out', [
			'actual_out' => '2026-07-01T16:10:00Z',
			'estimated_in' => '2026-07-02T00:55:00Z',
			'terminal_destination' => '4',
			'gate_destination' => 'B35',
		]);

		$this->assertSame('UA100 has left the gate at SFO', $cb->title);
		$this->assertSame('Arrives JFK 8:55 PM (25 min late) at Terminal 4, Gate B35.', $cb->body);
	}

	// =========================================================================
	public function test_took_off(): void {
		$cb = $this->makeCallback('departure', [
			'actual_off' => '2026-07-01T16:20:00Z',
			'estimated_in' => '2026-07-02T00:20:00Z',
		]);

		$this->assertSame('UA100 took off from SFO', $cb->title);
		$this->assertSame('Arrives JFK 8:20 PM (10 min early).', $cb->body);
	}

	// =========================================================================
	// Wheels-on is compared with the scheduled landing, not the later
	// scheduled gate arrival.
	public function test_landed(): void {
		$cb = $this->makeCallback('arrival', [
			'actual_on' => '2026-07-02T00:20:00Z',
			'terminal_destination' => '4',
		]);

		$this->assertSame('UA100 landed at JFK', $cb->title);
		$this->assertSame('Landed on time. Heading to Terminal 4.', $cb->body);
	}

	// =========================================================================
	public function test_at_gate(): void {
		$cb = $this->makeCallback('in', [
			'actual_in' => '2026-07-02T00:25:00Z',
			'gate_destination' => 'B35',
			'baggage_claim' => '5',
		]);

		$this->assertSame('UA100 is at the gate at JFK', $cb->title);
		$this->assertSame('Gate B35. Baggage claim 5.', $cb->body);
	}

	// =========================================================================
	public function test_cancelled(): void {
		$cb = $this->makeCallback('cancelled', ['cancelled' => true]);

		$this->assertSame('UA100 has been cancelled', $cb->title);
		$this->assertSame(
			'It was scheduled to leave SFO Wed 9:00 AM. Check with the airline about rebooking.',
			$cb->body
		);
	}

	// =========================================================================
	// The watched flight was going to JFK; the callback's destination is
	// where it's going now.
	public function test_diverted(): void {
		$flight = $this->makeFlight(['flight' => 'UA100']);
		$watch = Watch::create(['flight_id' => $flight->id]);
		$cb = $this->makeCallback('diverted', ['destination' => 'KSFO', 'destination_icao' => 'KSFO', 'destination_iata' => 'SFO'], save: false);
		$cb->watch_id = $watch->id;
		$cb->save();

		$this->assertSame('UA100 has been diverted', $cb->title);
		$this->assertSame(
			'It is now heading to SFO instead of JFK. Check with the airline for updates.',
			$cb->body
		);
	}

	// =========================================================================
	public function test_hold_start(): void {
		$cb = $this->makeCallback('hold_start', ['estimated_in' => '2026-07-02T00:50:00Z']);

		$this->assertSame('UA100 is in a holding pattern', $cb->title);
		$this->assertSame('Arrives JFK 8:50 PM (20 min late).', $cb->body);
	}

	// =========================================================================
	public function test_hold_end(): void {
		$cb = $this->makeCallback('hold_end', ['estimated_in' => '2026-07-02T00:50:00Z']);

		$this->assertSame('UA100 has left the holding pattern', $cb->title);
		$this->assertSame('Arrives JFK 8:50 PM (20 min late).', $cb->body);
	}

	// =========================================================================
	// No time in a hold payload, so sync shows when it was received rather
	// than scheduled_out.
	public function test_hold_has_no_event_time(): void {
		$this->assertNull($this->makeCallback('hold_start')->event_dt);
	}

	// =========================================================================
	public function test_change_names_a_new_departure_time(): void {
		$this->makeCallback('filed', ['estimated_out' => '2026-07-01T16:00:00Z']);
		$cb = $this->makeCallback('change', ['estimated_out' => '2026-07-01T16:40:00Z']);

		$this->assertSame('UA100 now departs 9:40 AM', $cb->title);
		$this->assertSame('Departs SFO 9:40 AM (40 min late). Arrives JFK 8:30 PM.', $cb->body);
	}

	// =========================================================================
	public function test_change_names_a_new_departure_gate(): void {
		$this->makeCallback('filed', ['estimated_out' => '2026-07-01T16:00:00Z', 'gate_origin' => 'F12']);
		$cb = $this->makeCallback('change', ['estimated_out' => '2026-07-01T16:00:00Z', 'gate_origin' => 'F14']);

		$this->assertSame('UA100 now departs from Gate F14', $cb->title);
	}

	// =========================================================================
	public function test_change_names_a_new_arrival_gate_after_departure(): void {
		$this->makeCallback('out', ['actual_out' => '2026-07-01T16:00:00Z', 'estimated_in' => '2026-07-02T00:30:00Z', 'gate_destination' => 'B30']);
		$cb = $this->makeCallback('change', ['actual_out' => '2026-07-01T16:00:00Z', 'estimated_in' => '2026-07-02T00:30:00Z', 'gate_destination' => 'B35']);

		$this->assertSame('UA100 now arrives at Gate B35', $cb->title);
		$this->assertSame('Arrives JFK 8:30 PM (on time) at Gate B35.', $cb->body);
	}

	// =========================================================================
	public function test_change_with_nothing_we_track_gives_the_next_time(): void {
		$fields = ['actual_out' => '2026-07-01T16:00:00Z', 'estimated_in' => '2026-07-02T00:30:00Z'];
		$this->makeCallback('out', $fields);
		$cb = $this->makeCallback('change', $fields);

		$this->assertSame('UA100 arrives 8:30 PM', $cb->title);
	}

	// =========================================================================
	// Only callbacks for the same flight instance count as the previous one.
	public function test_change_ignores_other_flights(): void {
		$this->makeCallback('filed', ['fa_flight_id' => 'UAL100-OTHER', 'gate_origin' => 'F12']);
		$cb = $this->makeCallback('change', ['estimated_out' => '2026-07-01T16:00:00Z', 'gate_origin' => 'F12']);

		$this->assertSame('UA100 now departs 9:00 AM', $cb->title);
	}

	// =========================================================================
	public function test_departure_delay(): void {
		$cb = $this->makeCallback('departure_delay', ['estimated_out' => '2026-07-01T17:00:00Z']);

		$this->assertSame('UA100 is running 1 hr late', $cb->title);
	}

	// =========================================================================
	public function test_arrival_delay(): void {
		$cb = $this->makeCallback('arrival_delay', ['estimated_in' => '2026-07-02T01:15:00Z']);

		$this->assertSame('UA100 will arrive 45 min late', $cb->title);
		$this->assertSame('Arrives JFK 9:15 PM.', $cb->body);
	}

	// =========================================================================
	// Arriving the next local day at the destination shows the weekday.
	public function test_overnight_arrival_shows_the_day(): void {
		$cb = $this->makeCallback('departure', [
			'scheduled_out' => '2026-07-02T04:00:00Z',	// Wed 9:00 PM PDT
			'scheduled_in' => '2026-07-02T12:30:00Z',	// Thu 8:30 AM EDT
			'estimated_in' => '2026-07-02T12:30:00Z',
		]);

		$this->assertSame('Arrives JFK Thu 8:30 AM (on time).', $cb->body);
	}

	// =========================================================================
	public function test_unknown_airport_uses_its_code_and_utc(): void {
		$cb = $this->makeCallback('departure', [
			'destination' => 'EGLL',
			'destination_icao' => 'EGLL',
			'destination_iata' => null,
			'estimated_in' => '2026-07-02T00:30:00Z',
		]);

		$this->assertSame('Arrives EGLL Thu 12:30 AM UTC (on time).', $cb->body);
	}

	// =========================================================================
	public function test_ident_without_iata_uses_the_icao_ident(): void {
		$cb = $this->makeCallback('departure', ['ident_iata' => null]);

		$this->assertSame('UAL100 took off from SFO', $cb->title);
	}

	// =========================================================================
	public function test_unhandled_event_uses_flightaware_text(): void {
		$cb = $this->makeCallback('airport_delay');

		$this->assertSame('UAL100 FlightAware summary', $cb->title);
		$this->assertSame('UAL100 FlightAware short description', $cb->body);
	}

	// =========================================================================
	public function test_missing_ident_uses_flightaware_text(): void {
		$cb = $this->makeCallback('departure', ['ident' => null, 'ident_iata' => null], save: false);

		$this->assertSame('UAL100 FlightAware summary', $cb->title);
	}

	// =========================================================================
	public function test_flat_airport_fields_are_stored(): void {
		$cb = $this->makeCallback('departure')->fresh();

		$this->assertSame('KSFO', $cb->origin_icao);
		$this->assertSame('SFO', $cb->origin_iata);
		$this->assertSame('KJFK', $cb->destination_icao);
		$this->assertSame('JFK', $cb->destination_iata);
	}

	// =========================================================================
	public function test_push_and_sync_use_the_new_text(): void {
		$flight = $this->makeFlight(['flight' => 'UA100', 'departure_date' => '2026-07-01']);
		$watch = Watch::create(['flight_id' => $flight->id]);
		$cb = $this->makeCallback('departure', ['estimated_in' => '2026-07-02T00:30:00Z'], save: false);
		$cb->watch_id = $watch->id;
		$cb->save();

		$fcm = (new Departure($cb))->toFcm(User::factory()->create());
		$this->assertSame('UA100 took off from SFO', $fcm['title']);
		$this->assertSame('Arrives JFK 8:30 PM (on time).', $fcm['body']);

		$sync = (new WatchCallbackSyncResource($cb))->toArray(request());
		$this->assertSame('UA100 took off from SFO', $sync['title']);
		$this->assertSame('UAL100 FlightAware summary', $sync['summary']);
	}
}
