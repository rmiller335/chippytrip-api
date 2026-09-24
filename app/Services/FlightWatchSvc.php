<?php

namespace App\Services;

use App\Jobs\EnableWatch;
use App\Models\Airline;
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
	// Stop the user watching a flight: remove their listener and those of
	// their family members. A watch left with no listeners is cleaned up,
	// with its flight and callbacks, by maintenance:nightly. Returns false
	// when the user isn't watching the flight.
	public function removeListener(Flight $flight, User $user): bool {
		$watch = $flight->watch;

		if (null == $watch || ! $watch->listeners()->where('user_id', $user->id)->exists()) {
			return false;
		}

		$userIds = $user->family()->pluck('users.id')->push($user->id);
		$watch->listeners()->whereIn('user_id', $userIds)->delete();

		return true;
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
	// The airlines a flight number could belong to, each with the number
	// without its airline code. Accepts ICAO (UAL100) or IATA (UA100, B6123)
	// idents. Some IATA codes are shared by more than one airline in our
	// table, so there can be several; empty means the code isn't known.
	//
	// @return list<array{Airline, string}>
	public static function airlinesForIdent(string $ident): array {
		$ident = strtoupper(trim($ident));

		// ICAO codes are three letters; IATA codes are two characters and
		// may include a digit (B6, 9E). A letter third means ICAO.
		$isIcao = strlen($ident) > 3 && ctype_alpha(substr($ident, 0, 3));
		$code = substr($ident, 0, $isIcao ? 3 : 2);
		$number = substr($ident, $isIcao ? 3 : 2);

		if (! preg_match('/^\d+[A-Z]?$/', $number)) {
			return [];
		}

		return Airline::where($isIcao ? 'icao' : 'iata', $code)
			->orderBy('icao')
			->get()
			->map(fn (Airline $airline) => [$airline, $number])
			->all();
	}

	// =========================================================================
	// Find the flight matching this ident/route/date, or create it from an
	// AeroAPI schedule lookup. Flights are stored under their IATA flight
	// number (ICAO if the airline has no IATA code). Returns null when the
	// airline isn't known or AeroAPI has no matching scheduled flight.
	public function findOrCreateFlight(
		string $ident,
		string $originIcao,
		string $destinationIcao,
		string $date,
		?string $departureLocal = null,
	): ?Flight {
		$candidates = array_map(fn ($c) => [
			'airline' =>	$c[0],
			'number' =>		$c[1],
			'flight' =>		($c[0]->iata ?: $c[0]->icao) . $c[1],
		], self::airlinesForIdent($ident));

		foreach ($candidates as $c) {
			$flightRec = Flight::where('flight', $c['flight'])
				->where('origin_icao', $originIcao)
				->where('destination_icao', $destinationIcao)
				->whereDate('departure_date', $date)
				->first()
			;

			if (null != $flightRec) {
				return $flightRec;
			}
		}

		foreach ($candidates as $c) {
			$flightRec = Flight::make([
				'airline_icao' =>		$c['airline']->icao,
				'departure_date' =>	$date,
				'departure_dt' =>		$departureLocal,
				'destination_icao' =>	$destinationIcao,
				'flight_no' =>			$c['number'],
				'flight' =>				$c['flight'],
				'origin_icao' =>		$originIcao,
			]);

			$info = $this->fa->flightSchedule($flightRec);

			Log::debug('FlightAwareSvc::flightSchedule() returned:');
			Log::debug(json_encode($info, JSON_PRETTY_PRINT));

			if (null != $info) {
				break;
			}
		}

		if (empty($info)) {
			return null;
		}

		$flightRec->departure_dt =		new Carbon($info->scheduled_out);
		$flightRec->arrival_dt =		new Carbon($info->scheduled_in);
		$flightRec->equipment =		$info->aircraft_type ?? null;
		$flightRec->meal_service =		$info->meal_service ?? null;
		$flightRec->first_seats =		$info->seats_cabin_first ?? null;
		$flightRec->business_seats =	$info->seats_cabin_business ?? null;
		$flightRec->coach_seats =		$info->seats_cabin_coach ?? null;

		$flightRec->save();

		return $flightRec;
	}
}
