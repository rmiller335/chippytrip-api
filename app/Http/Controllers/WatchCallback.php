<?php

namespace App\Http\Controllers;

use App\Jobs\NotificationsIndex;
use App\Jobs\SendNotification;
use App\Models\Watch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// =============================================================================
class WatchCallback extends Controller {
	// =========================================================================
	public function callback(Request $request) {
		Log::debug("WatchCallback::callback() called with payload: " .
			json_encode($request->json()->all(), JSON_PRETTY_PRINT)
		);
		$secret = $request->input('s');

		$wc = \App\Models\WatchCallback::FromApiPayload($request->json()->all(), $request->ip());

		$watch = Watch::where('subscription_id', $wc->alert_id)->first();

		if(null == $watch || $watch->secret != $secret) {
			return response('Invalid POST request', 403);
		}

		$wc->watch_id = $watch->id;
		$wc->save();

		if (! \App\Models\WatchCallback::handles($wc->event_code)) {
			Log::warning("WatchCallback: no notification for event '{$wc->event_code}'", [
				'callback_id' =>	$wc->id,
				'watch_id' =>		$watch->id,
			]);
		}

		$this->logWindow($wc, $watch);

		if($wc->matchesFlightDate($watch->flight)) {
			foreach($wc->watch->listeners as $listener) {
				Bus::chain([
					new SendNotification($wc, $listener->user),
					new NotificationsIndex(),
				])->dispatch();
			}
		}

		return response(null, 200);
	}

	// =========================================================================
	// Feeds alerts:window-report. Never lets a logging problem fail the webhook.
	private function logWindow(\App\Models\WatchCallback $wc, Watch $watch): void {
		try {
			DB::table('alert_window_log')->insert([
				'watch_id' =>		$watch->id,
				'callback_id' =>	$wc->id,
				'ident' =>			$wc->ident_iata ?? $wc->ident_icao,
				'fa_flight_id' =>	$wc->fa_flight_id,
				'event_code' =>		$wc->event_code,
				'watched_date' =>	$watch->flight->departure_date->toDateString(),
				'scheduled_out' =>	$wc->scheduled_out,
				'offset_days' =>	$wc->flightDateOffset($watch->flight),
				'created_at' =>		now(),
			]);
		}
		catch (\Throwable $e) {
			Log::warning('WatchCallback: alert_window_log insert failed: ' . $e->getMessage());
		}
	}
}
