<?php

namespace App\Http\Controllers;

use App\Http\Resources\Sync\ListenerSyncResource;
use App\Http\Resources\Sync\WatchSyncResource;
use App\Http\Resources\Sync\WatchCallbackSyncResource;
use App\Http\Resources\Sync\FlightSyncResource;
use App\Http\Resources\Sync\AirlineSyncResource;
use App\Http\Resources\Sync\AirportSyncResource;

use Illuminate\Http\Request;

// =============================================================================
class ListenerSyncController extends Controller {
	// =========================================================================
	public function index(Request $request) {
		$listeners = $request->user()
			->listeners()
			->whereHas('watch', fn ($q) => $q->where('enabled', true))
			->with([
				'watch.flight.airline',
				'watch.flight.origin',
				'watch.flight.destination',
				'watch.callbacks'
			])
			->get();

		$watches   = $listeners->pluck('watch')->filter()->unique('id');
		$flights   = $watches->pluck('flight')->filter()->unique('id');
		$airlines  = $flights->pluck('airline')->filter()->unique('icao');
		$airports  = $flights->pluck('origin')
			->merge($flights->pluck('destination'))
			->filter()
			->unique('icao');
		$watch_callbacks = $watches->flatMap(fn ($watch) => $watch->callbacks->each(
				fn ($callback) => $callback->flight_id = $watch->flight_id
			))
			->filter()
			->unique('id');

		return response()->json([
			'synced_at' =>				now()->toIso8601String(),
			'listeners' =>				ListenerSyncResource::collection($listeners),
			'watches' =>				WatchSyncResource::collection($watches),
			'flight_notifications' =>	WatchCallbackSyncResource::collection($watch_callbacks),
			'flights' =>				FlightSyncResource::collection($flights),
			'airlines' =>				AirlineSyncResource::collection($airlines),
			'airports' =>				AirportSyncResource::collection($airports),
		]);
	}
}
