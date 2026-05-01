<?php

namespace App\Console\Commands;

use App\Services\FlightLookupSvc;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

// =============================================================================
#[Signature('flights:lookup {airline} {flightno} {origin} {destination} {date}')]
#[Description('Lookup a flight')]
class FlightsLookup extends Command {
	// =========================================================================
    public function handle(FlightLookupSvc $fl) {
		$airline =		$this->argument('airline');
		$flightno =		$this->argument('flightno');
		$origin =		$this->argument('origin');
		$destination =	$this->argument('destination');
		$date =			$this->argument('date');

		$info = $fl->flight($airline, $flightno, $origin, $destination, $date);

		Log::debug(json_encode($info, JSON_PRETTY_PRINT));
    }
}
