<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

// =============================================================================
class FcmTokenController extends Controller {
	// =========================================================================
    public function store(Request $request) {
		Log::debug('FcmTokenController: request body = ...');
		Log::debug(json_encode($request->all(), JSON_PRETTY_PRINT));

        $request->validate([
            'token' => 'required|string',
            'device_id' => 'required|string',
        ]);

        $request->user()->channels()->updateOrCreate(
            ['channel' => \App\Notifications\Channels\FcmChannel::class, 'identifier' => $request->device_id],
            ['credentials' => [ 'token' => $request->token ]]
        );

        return response()->noContent();
    }

	// =========================================================================
	// Stop push notifications to one of the user's devices (e.g. on sign
	// out). Idempotent: succeeds even if the device has no token registered.
	public function destroy(Request $request) {
		$request->validate([
			'device_id' => 'required|string',
		]);

		$request->user()->channels()
			->where('channel', \App\Notifications\Channels\FcmChannel::class)
			->where('identifier', $request->device_id)
			->delete();

		return response()->noContent();
	}
}
