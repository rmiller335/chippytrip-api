<?php

namespace App\Console\Commands;

use App\Models\Flight;
use App\Models\Watch;
use App\Services\FlightAwareSvc;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

// =============================================================================
#[Signature('watch:create {flight-id}')]
#[Description('Command description')]
class WatchCreate extends Command {
	// =========================================================================
    public function handle(FlightAwareSvc $fa) {
		$flightId = $this->argument('flight-id');

		$flight = Flight::find($flightId);
		$secret = bin2hex(random_bytes(16));

		$subsId = $fa->watchCreate($flight->flight, $flight->origin_icao,
			$flight->destination_icao, $flight->departure_date, $secret);

		if($subsId) {
			Watch::create([
				'flight_id' =>	$flightId,
				'subscription_id' =>	$subsId,
			]);
		}
		else {
		}
    }
}
