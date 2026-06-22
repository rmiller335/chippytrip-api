<?php

namespace App\Http\Controllers;

use App\Jobs\NotificationsIndex;
use App\Jobs\SendNotification;
use App\Models\Watch;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

// =============================================================================
class WatchCallback extends Controller {
	// =========================================================================
	public function callback(Request $request) {
		$secret = $request->input('s');

		$wc = \App\Models\WatchCallback::FromApiPayload($request->json()->all(), $request->ip());

		$watch = Watch::where('subscription_id', $wc->alert_id)->first();

		if(null == $watch || $watch->secret != $secret) {
			return response('Invalid POST request', 403);
		}

		$wc->save();

		$depDate = $wc->scheduled_out->copy()->startOfDay();

		if($watch->flight->departure_date->eq($depDate)) {
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
	public function watch(): BelongsTo {
		return $this->belongsTO(Watch::class, 'subscription_id', 'alert_id');
	}
}
