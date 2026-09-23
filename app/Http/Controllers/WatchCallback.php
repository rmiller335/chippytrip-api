<?php

namespace App\Http\Controllers;

use App\Jobs\NotificationsIndex;
use App\Jobs\SendNotification;
use App\Models\Watch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
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

		$wc->save();

		// departure_date is a local calendar date (from the user's search or
		// confirmation email), but scheduled_out is always UTC — comparing
		// them directly breaks for late-night departures where the UTC date
		// has already rolled over relative to the origin airport's local
		// date. Convert scheduled_out into the origin's local timezone
		// before comparing calendar dates.
		$originTz = $watch->flight->origin->timezone ?? 'UTC';
		$localScheduledOut = $wc->scheduled_out->copy()->setTimezone($originTz);

		if($watch->flight->departure_date->toDateString() === $localScheduledOut->toDateString()) {
			foreach($wc->watch->listeners as $listener) {
				Bus::chain([
					new SendNotification($wc, $listener->user),
					new NotificationsIndex(),
				])->dispatch();
			}
		}

		return response(null, 200);
	}
}
