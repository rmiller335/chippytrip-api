<?php

namespace App\Console\Commands;

use App\Models\Flight;
use App\Models\Listener;
use App\Models\Watch;
use App\Services\FlightAwareSvc;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// =============================================================================
#[Signature('maintenance:nightly')]
#[Description('Command description')]
class MaintenanceNightly extends Command {
	protected array $alerts;

	// =========================================================================
	protected function disableOld() {
		$now = Carbon::now('UTC');

		$old = Listener::with('watch.flight')
			->whereHas('watch', function($q) {
				return $q->where('enabled', true);
			})
			->whereHas('watch.flight', function($q) use($now) {
				return $q->where('alert_start', '>', $now->endOfDay())
					->orWhere('alert_end', '<', $now->startOfDay())
				;
			})
		;

		foreach($old->lazy(200) as $l) {
			$this->fa->deleteWatch($l->watch->subscription_id);
			$l->update([ 'enabled' => false ]);
		}
	}

	// =========================================================================
	protected function enableNew() {
		$now = Carbon::now('UTC');

		$new = Listener::with('watch.flight')
			->whereHas('watch', function($q) {
				return $q->where('enabled', false);
			})
			->whereHas('watch.flight', function($q) use($now) {
				return $q->where('alert_start', '<=', $now->endOfDay())
					->andWhere('alert_end', '>=', $now->startOfDay())
				;
			})
		;

		foreach($new->lazy(200) as $l) {
//			$this->fa->deleteWatch($l->watch->subscription_id);
			$l->update([ 'enabled' => true ]);
		}
	}

	// =========================================================================
    public function handle(FlightAwareSvc $fa) {
		$this->alerts = $fa->watchList();

		DB::transaction(function() {
			$this->syncAlerts();
			$this->disableOld();
		});
    }

	// =========================================================================
	protected function syncAlerts() {
		Watch::query()->update([ 'enabled' => false ]);

		foreach($this->alerts as $alert) {
			$watch = Watch::where('subscription_id', $alert->id)->first();

			if(null != $watch) {
				$watch->update([ 'enabled' => true ]);
			}
		}
	}
}
