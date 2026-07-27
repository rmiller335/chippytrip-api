<?php

namespace Database\Seeders;

use App\Models\Airline;
use App\Models\Airport;
use App\Models\Flight;
use App\Models\Listener;
use App\Models\User;
use App\Models\Watch;
use App\Models\WatchCallback;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

// =============================================================================
// Builds one fully-linked Flight/Watch/Listener/User/WatchCallback chain out
// of the reference data (airlines/airports/countries) already in the test
// database, so tests that expect real-looking data don't need live API calls.
class TestFixtureSeeder extends Seeder {
	// =========================================================================
    public function run(): void {
		$flight = Flight::where('flight', 'TEST100')->first();

		if($flight) {
			return;
		}

		$airline = Airline::whereNotNull('icao')->first();
		$origin = Airport::whereNotNull('icao')->first();
		$destination = Airport::whereNotNull('icao')->where('id', '!=', $origin->id)->first();

		$flight = Flight::create([
			'airline_icao' =>		$airline->icao,
			'flight' =>				'TEST100',
			'flight_no' =>			'100',
			'origin_icao' =>		$origin->icao,
			'destination_icao' =>	$destination->icao,
			'departure_date' =>		Carbon::today(),
		]);

		$watch = Watch::create([
			'flight_id' =>			$flight->id,
			'subscription_id' =>	900000001,
			'secret' =>				Watch::genSecret(),
			'enabled' =>			true,
		]);

		$user = User::factory()->create([
			'name' =>	'Test Fixture User',
			'email' =>	'fixtures@example.test',
		]);

		$listener = new Listener(['travelers' => 'Test Traveler']);
		$listener->watch_id = $watch->id;
		$listener->user_id = $user->id;
		$listener->save();

		WatchCallback::create([
			'alert_id' =>			$watch->subscription_id,
			'event_code' =>			'departure',
			'summary' =>			"{$flight->flight} departed",
			'short_description' =>	'Flight has departed',
			'long_description' =>	"Flight {$flight->flight} has departed the origin airport.",
			'fa_flight_id' =>		'TEST100-FIXTURE',
			'ident' =>				$flight->flight,
			'origin' =>				$origin->icao,
			'origin_icao' =>		$origin->icao,
			'origin_iata' =>		$origin->iata,
			'destination' =>		$destination->icao,
			'destination_icao' =>	$destination->icao,
			'destination_iata' =>	$destination->iata,
			'scheduled_out' =>		$flight->departure_date->copy()->setTime(14, 0, 0),
			'actual_out' =>			$flight->departure_date->copy()->setTime(14, 0, 0),
			'raw_payload' =>		['fixture' => true],
		]);
    }
}
