<?php

namespace App\Http\Controllers;

use App\Models\WatchInfo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

// =============================================================================
class WatchCallback extends Controller {
	// =========================================================================
	public function callback(Request $request) {
		Log::debug("WatchCallback::calback ...");

		Log::debug("s = " . $request->input('s'));
		Log::debug("Long description: " . $request->input('long_description'));

		$wi = WatchInfo::FromApiPayload($request->json()->all(), $request->ip());
		$wi->save();

		return response(null, 200);
	}
}
