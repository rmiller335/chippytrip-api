<?php

namespace App\Console\Commands;

use App\Jobs\DisableWatch;
use App\Jobs\EnableWatch;
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
    protected FlightAwareSvc $fa;

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
			DisableWatch::dispatch($l->watch);
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
				return $q->where('alert_start', '<=', $now->startOfDay())
					->where('alert_end', '>=', $now->endOfDay())
				;
			})
		;

		Log::debug("enableNew: found " . $new->count() . " new");

		foreach($new->lazy(200) as $l) {
			EnableWatch::dispatch($l->watch);
		}
	}

	// =========================================================================
    public function handle(FlightAwareSvc $fa) {
		$this->fa = $fa;
		$this->alerts = $this->fa->watchList();

		DB::transaction(function() {
			$this->syncAlerts();
			$this->disableOld();
			$this->enableNew();
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
