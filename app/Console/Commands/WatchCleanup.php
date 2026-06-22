<?php

namespace App\Console\Commands;

use App\Models\Watch;
use App\Services\FlightAwareSvc;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

// =============================================================================
class WatchCleanup extends Command {
    protected $signature = 'watch:cleanup';
    protected $description = 'Remove orphan watches on FlightAware';

	// =========================================================================
    public function handle(FlightAwareSvc $fa) {
		$list = $fa->watchList();

		foreach($list as $alert) {
			$watch = Watch::where('subscription_id', $alert->id)->first();

			if(null == $watch) {
				$fa->watchDelete($alert->id);
			}
		}

		return Command::SUCCESS;
    }
}
