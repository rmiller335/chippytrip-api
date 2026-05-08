<?php

namespace App\Http\Controllers;

use App\Jobs\SendNotification;
use App\Models\Watch;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

// =============================================================================
class WatchCallback extends Controller {
	// =========================================================================
	public function callback(Request $request) {
		Log::debug("WatchCallback::calback ...");

		Log::debug("s = " . $request->input('s'));
		Log::debug("Long description: " . $request->input('long_description'));

		$wc = \App\Models\WatchCallback::FromApiPayload($request->json()->all(), $request->ip());
		$wc->save();

		foreach($wc->watch->listeners as $listener) {
			$job = new SendNotification($wc, $listener->user);
			dispatch($job);
		}

		return response(null, 200);
	}

	// =========================================================================
	public function watch(): BelongsTo {
		return $this->belongsTO(Watch::class, 'subscription_id', 'alert_id');
	}
}
