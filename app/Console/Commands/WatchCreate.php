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
		$secret = Watch::genSecret();

		$watchStart = Carbon::max($flight->departure_date->subDay(), Carbon::today());

		$subsId = $fa->watchCreate($flight->flight, $flight->origin_icao,
			$flight->destination_icao, $watchStart, $secret);

		if($subsId) {
			Watch::create([
				'flight_id' =>			$flightId,
				'subscription_id' =>	$subsId,
				'secret' =>				$secret,
			]);
		}
		else {
		}
    }
}
