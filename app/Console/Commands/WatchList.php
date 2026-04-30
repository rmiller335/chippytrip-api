<?php

namespace App\Console\Commands;

use App\Models\Watch;
use App\Services\FlightAwareSvc;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

// =============================================================================
class WatchList extends Command {
    protected $signature = 'watch:list';
    protected $description = 'List watches on FlightAware';

	// =========================================================================
    public function handle(FlightAwareSvc $fa) {
		$list = $fa->watchList();

		$headers = [ 'Id', ' Subs Id', 'Flight', 'Origin', 'Destination', 'Start', 'End' ];

		$data = [];

		foreach($list->alerts as $alert) {
			$watch = Watch::where('subscription_id', $alert->id)->first();

			$data[] = [
				$watch ? $watch->id : '',
				$alert->id,
				$alert->ident,
				$alert->origin_icao,
				$alert->destination_icao,
				$alert->start,
				$alert->end,
			];
		}

		$this->table($headers, $data);

		return Command::SUCCESS;
    }
}
