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

		$watch = Watch::create([
			'flight_id' =>		$flightId,
			'enabled' =>		false,
		]);

		if($watch->watchable()) {
			EnableWatch::dispatch($watch);
		}
    }
}
