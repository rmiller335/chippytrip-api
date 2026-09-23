<?php

namespace App\Services;

use App\Jobs\EnableWatch;
use App\Models\Flight;
use App\Models\Listener;
use App\Models\User;
use App\Models\Watch;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

// =============================================================================
// Shared by ParseConfirmationEmail (flights found in a forwarded confirmation
// email) and FlightSearchController (flights a user adds directly via the
// API) so the find-or-create-flight / find-or-create-watch / find-or-create-
// listener logic exists in exactly one place.
class FlightWatchSvc {
	// =========================================================================
	public function __construct(private readonly FlightAwareSvc $fa) {
	}

	// =========================================================================
	// Find-or-create the watch for a flight, and find-or-create a listener on
	// it for the given user. Enables the watch immediately if the flight is
	// already within its alert window.
	public function addListener(Flight $flight, User $user, ?string $travelers = null): Listener {
		$watch = $flight->watch ?? $flight->watch()->create([
			'enabled' => false,
		]);

		$listener = $watch->listeners()->firstOrCreate(
			['user_id' => $user->id],
			['travelers' => $travelers ?: $user->name],
		);

		$this->addAutoFamilyListeners($watch, $user);

		// Only ever enable a watch once. Re-dispatching EnableWatch for a
		// watch that's already enabled creates a brand-new FlightAware alert
		// and overwrites subscription_id, silently orphaning the old alert:
		// its still-in-flight webhooks (e.g. a later arrival event) get
		// rejected by WatchCallback::callback() once subscription_id no
		// longer matches, and the same physical event can end up delivered
		// (and notified) twice while both alerts are briefly live.
		if (! $watch->enabled && $watch->watchable()) {
			EnableWatch::dispatch($watch);
		}

		return $listener;
	}

	// =========================================================================
	// Add a listener for each of the user's family members flagged to be
	// auto-added whenever this user starts watching a flight.
	private function addAutoFamilyListeners(Watch $watch, User $user): void {
		$autoFamily = $user->family()->wherePivot('auto_add', true)->get();

		foreach ($autoFamily as $familyMember) {
			$watch->listeners()->firstOrCreate(
				['user_id' => $familyMember->id],
				['travelers' => $familyMember->pivot->name],
			);
		}
	}

	// =========================================================================
	// Find the flight matching this ident/route/date, or create it from an
	// AeroAPI schedule lookup. Returns null when AeroAPI has no matching
	// scheduled flight.
	public function findOrCreateFlight(
		string $ident,
		string $originIcao,
		string $destinationIcao,
		string $date,
		?string $departureLocal = null,
	): ?Flight {
		$flightRec = Flight::where('flight', $ident)
			->where('origin_icao', $originIcao)
			->where('destination_icao', $destinationIcao)
			->whereDate('departure_date', $date)
			->first()
		;

		if (null != $flightRec) {
			return $flightRec;
		}

		$flightRec = Flight::make([
			'airline_icao' =>		Flight::icaoFromFlightNum($ident),
			'departure_date' =>	$date,
			'departure_dt' =>		$departureLocal,
			'destination_icao' =>	$destinationIcao,
			'flight_no' =>			substr($ident, 2),
			'flight' =>				$ident,
			'origin_icao' =>		$originIcao,
		]);

		$info = $this->fa->flightSchedule($flightRec);

		Log::debug('FlightAwareSvc::flightSchedule() returned:');
		Log::debug(json_encode($info, JSON_PRETTY_PRINT));

		if (null == $info) {
			return null;
		}

		$flightRec->departure_dt =		new Carbon($info->scheduled_out);
		$flightRec->arrival_dt =		new Carbon($info->scheduled_in);
		$flightRec->equipment =		$info->aircraft_type;
		$flightRec->meal_service =		$info->meal_service;
		$flightRec->first_seats =		$info->seats_cabin_first;
		$flightRec->business_seats =	$info->seats_cabin_business;
		$flightRec->coach_seats =		$info->seats_cabin_coach;

		$flightRec->save();

		return $flightRec;
	}
}
