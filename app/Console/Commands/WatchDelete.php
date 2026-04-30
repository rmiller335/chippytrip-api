<?php

namespace App\Console\Commands;

use App\Models\Watch;
use App\Services\FlightAwareSvc;
use Illuminate\Console\Command;

// =============================================================================
class WatchDelete extends Command {
    protected $signature = 'watch:delete {id}';
    protected $description = 'Delete FlightAware alert';

	// =========================================================================
    public function handle(FlightAwareSvc $fa) {
		$id = $this->argument('id');

		$fa->watchDelete($id);

		$watch = Watch::where('subscription_id', $id)->first();

		if(null != $watch) {
			$watch->delete();
		}
    }
}
