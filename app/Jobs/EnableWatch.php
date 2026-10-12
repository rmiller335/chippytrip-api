<?php

namespace App\Jobs;

use App\Models\Watch;
use App\Services\FlightAwareSvc;
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

		// The alert covers the watched departure (plus a buffer), whenever it's
		// created: the day before via maintenance:nightly, or right away.
		[$start, $end] = $this->watch->flight->alertWindow();

		$subsId = $fa->watchCreate(
			$this->watch->flight->flight,
			$this->watch->flight->origin_icao,
			$this->watch->flight->destination_icao,
			$start,
			$end,
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
