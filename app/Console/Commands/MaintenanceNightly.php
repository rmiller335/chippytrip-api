<?php

namespace App\Console\Commands;

use App\Jobs\DisableWatch;
use App\Jobs\EnableWatch;
use App\Models\EmailRelatedRecord;
use App\Models\Flight;
use App\Models\Listener;
use App\Models\Watch;
use App\Models\WatchCallback;
use App\Services\FlightAwareSvc;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// =============================================================================
#[Signature('maintenance:nightly')]
#[Description('Command description')]
class MaintenanceNightly extends Command {
	// When the last run finished, for the /health check.
	public const LAST_RUN_KEY = 'health:maintenance_last_run';

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
			if($l->watch instanceof Watch) {
				DisableWatch::dispatch($l->watch);
			}
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

		foreach($new->lazy(200) as $l) {
			if($l->watch instanceof Watch) {
				EnableWatch::dispatch($l->watch);
			}
		}
	}

	// =========================================================================
    public function handle(FlightAwareSvc $fa) {
		DB::transaction(function() use($fa){
			$this->disableOld();
			$this->enableNew();
			$this->pruneUnwatched();
			$this->pruneAlerts($fa);
		});

		Cache::forever(self::LAST_RUN_KEY, now()->toIso8601String());
    }

	// =========================================================================
	protected function pruneAlerts(FlightAwareSvc $fa) {
		$alerts = $fa->watchList();

		foreach($alerts as $alert) {
			$watch = Watch::where('subscription_id', $alert->id)->first();

			if(null == $watch) {
				$fa->watchDelete($alert->id);
			}
		}
	}

	// =========================================================================
	// Delete watches no one listens to any more (see
	// FlightWatchSvc::removeListener()) and flights left without a watch.
	// Callbacks and watches_notifications rows cascade with the watch; the
	// watch's FlightAware alert no longer matches a watch, so pruneAlerts()
	// deletes it.
	//
	// Each delete re-checks its condition in the same statement, so a
	// listener or watch added since the last run keeps its rows.
	protected function pruneUnwatched() {
		Watch::whereDoesntHave('listeners')->delete();

		$unwatched = Flight::whereDoesntHave('watch');

		EmailRelatedRecord::where('record_type', Flight::class)
			->whereIn('record_id', (clone $unwatched)->select('id'))
			->delete()
		;
		$unwatched->delete();

		// Callbacks from before watch_id existed that couldn't be matched to
		// a watch.
		WatchCallback::whereNull('watch_id')->delete();
	}
}
