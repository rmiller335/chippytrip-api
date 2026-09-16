<?php

namespace App\Http\Controllers;

use App\Http\Resources\Sync\FlightSyncResource;
use App\Http\Resources\Sync\ListenerSyncResource;
use App\Http\Resources\Sync\WatchSyncResource;
use App\Models\Airline;
use App\Models\Airport;
use App\Services\FlightAwareSvc;
use App\Services\FlightWatchSvc;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

// =============================================================================
class FlightSearchController extends Controller {
	// =========================================================================
	private function addNames($flight) {
		Log::debug('FlightSearchController::addNames: adding names to flight ...');
		Log::debug(json_encode($flight, JSON_PRETTY_PRINT));

		$flight['origin_name'] = Airport::getAirportName(
			$flight['origin_icao'], $flight['origin_iata']
		);

		$flight['destination_name'] = Airport::getAirportName(
			$flight['destination_icao'], $flight['destination_iata']
		);

		$flight['airline_name'] = Airline::getAirlineName(
			$flight['airline_icao'], $flight['airline_iata']
		);

		return $flight;
	}

	// =========================================================================
	public function checkFlight(Request $request, FlightAwareSvc $flightAware) {
		$request->validate([
			'flight_number' => 'required|string',
			'date' => 'required|date|after_or_equal:today',
		]);

		$date = Carbon::parse($request->date);
		$ident = strtoupper(trim($request->flight_number));

		Log::debug('FlightSearchController::checkFlight: looking up flight', [
			'flight_number' => $ident,
			'date' => $request->date,
		]);

		try {
			$matches = $flightAware->scheduleForIdent($ident, $date);
		} catch (\Throwable $e) {
			Log::warning('FlightSearchController::checkFlight: AeroAPI lookup failed', [
				'flight_number' => $ident,
				'date' => $request->date,
				'error' => $e->getMessage(),
			]);

			return response()->json(['message' => 'Could not reach FlightAware.'], 502);
		}

		$flights = collect($matches)
			->map(fn ($f) => $this->normalize($f))
			->values()
		;

		return response()->json($flights);
	}

	// =========================================================================
	public function search(Request $request, FlightAwareSvc $flightAware) {
		$request->validate([
			'origin' => 'required|string',
			'destination' => 'required|string',
			'date' => 'required|date|after_or_equal:today',
			'airline' => 'nullable|string',
		]);

		$date = Carbon::parse($request->date);

		try {
			$scheduled = $flightAware->scheduleByRoute(
				$date,
				$request->origin,
				$request->destination,
				$request->airline,
			);
		} catch (\Throwable $e) {
			Log::warning('FlightSearchController::search: AeroAPI lookup failed', [
				'origin' => $request->origin,
				'destination' => $request->destination,
				'date' => $request->date,
				'airline' => $request->airline,
				'error' => $e->getMessage(),
			]);

			return response()->json(['message' => 'Could not reach FlightAware.'], 502);
		}

		Log::debug('FlightSearchController::search: found scheduled flights:');
		Log::debug(json_encode($scheduled, JSON_PRETTY_PRINT));

		$flights = collect($scheduled)
			->map(fn ($f) => $this->normalize($f))
			->map(fn ($f) => $this->addNames($f))
			->values()
		;

		return response()->json($flights);
	}

	// =========================================================================
	// Add a flight to watch for notifications. Finds or creates the Flight,
	// finds or creates its Watch, and finds or creates a Listener on that
	// watch for the logged in user.
	public function watch(Request $request, FlightWatchSvc $flightWatchSvc) {
		$request->validate([
			'flight_number' => 'required|string',
			'origin' => 'required|string',
			'destination' => 'required|string',
			'date' => 'required|date|after_or_equal:today',
			'travelers' => 'nullable|string',
		]);

		$ident = strtoupper(trim($request->flight_number));
		$origin = strtoupper(trim($request->origin));
		$destination = strtoupper(trim($request->destination));
		$date = Carbon::parse($request->date);

		Log::debug('FlightSearchController::watch: adding flight for notifications', [
			'flight_number' => $ident,
			'origin' => $origin,
			'destination' => $destination,
			'date' => $request->date,
		]);

		try {
			$flight = $flightWatchSvc->findOrCreateFlight(
				$ident,
				$origin,
				$destination,
				$date->toDateString(),
			);
		} catch (\Throwable $e) {
			Log::warning('FlightSearchController::watch: AeroAPI lookup failed', [
				'flight_number' => $ident,
				'origin' => $origin,
				'destination' => $destination,
				'date' => $request->date,
				'error' => $e->getMessage(),
			]);

			return response()->json(['message' => 'Could not reach FlightAware.'], 502);
		}

		if (null == $flight) {
			return response()->json(['message' => 'No matching scheduled flight found.'], 404);
		}

		$listener = $flightWatchSvc->addListener(
			$flight,
			$request->user(),
			$request->travelers
		);

		return response()->json([
			'flight' =>		new FlightSyncResource($flight),
			'watch' =>		new WatchSyncResource($listener->watch),
			'listener' =>	new ListenerSyncResource($listener),
		]);
	}

	// =========================================================================
	protected function normalize(object $flight): array {
		$identIcao = $flight->ident_icao ?? $flight->ident ?? null;
		$identIata = $flight->ident_iata ?? null;

		// ICAO airline designators are always exactly 3 letters; IATA
		// designators are always exactly 2 characters and may include a
		// digit (e.g. B6 = JetBlue, 9E = Endeavor).
		$airlineIcao = $identIcao ? substr($identIcao, 0, 3) : null;
		$airlineIata = $identIata ? substr($identIata, 0, 2) : null;

		$flightNo = $identIata && $airlineIata
			? substr($identIata, strlen($airlineIata))
			: ($identIcao && $airlineIcao ? substr($identIcao, strlen($airlineIcao)) : null);

		return [
			'flight_number' =>		$identIata ?? $identIcao,
			'flight_no' =>			$flightNo,
			'airline_icao' =>		$airlineIcao,
			'airline_iata' =>		$airlineIata,
			'origin_icao' =>		$flight->origin_icao ?? $flight->origin ?? null,
			'origin_iata' =>		$flight->origin_iata ?? null,
			'destination_icao' =>	$flight->destination_icao ?? $flight->destination ?? null,
			'destination_iata' =>	$flight->destination_iata ?? null,
			'departure_time' =>		FlightAwareSvc::fixDT($flight->scheduled_out ?? null),
		];
	}
}
