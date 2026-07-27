<?php

namespace App\Console\Commands;

use App\Services\FlightAwareSvc;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

// =============================================================================
#[Signature('watch:clear')]
#[Description('Clear all flightaware alerts')]
class WatchClear extends Command {
	// =========================================================================
    public function handle(FlightAwareSvc $fa) {
		$alerts = $fa->watchList();

		foreach($alerts as $alert) {
			$fa->watchDelete($alert->id);
		}
    }
}
