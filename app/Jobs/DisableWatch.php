<?php

namespace App\Jobs;

use App\Models\Watch;
use App\Services\FlightAwareSvc;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

// =============================================================================
class DisableWatch implements ShouldQueue {
    use Queueable;

	// =========================================================================
    public function __construct(public Watch $watch) {
    }

	// =========================================================================
    public function handle(FlightAwareSvc $fa): void {
		$fa->watchDelete($this->watch->subscription_id);

		$this->watch->disable();
		$this->watch->save();
    }
}
