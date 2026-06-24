<?php

namespace App\Jobs;

use App\Models\Watch;
use App\Services\FlightAwareSvc;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

// =============================================================================
class EnableWatch implements ShouldQueue {
    use Queueable;

	// =========================================================================
    public function __construct(public Watch $watch) {
    }

	// =========================================================================
    public function handle(FlightAwareSvc $fa): void {
		$secret = Watch::genSecret();

		$start = max(Carbon::now(), $this->watch->flight->alert_start);

		$subsId = $fa->watchCreate(
			$this->watch->flight->flight,
			$this->watch->flight->origin_icao,
			$this->watch->flight->destination_icao,
			Carbon::now(),
			$secret
		);

		if($subsId) {
			$this->watch->enable($subsId, $secret);
			$this->watch->save();
		}
		else {
			Log::error("Unable to create FA Alert");

			$this->fail();
		}
    }
}
