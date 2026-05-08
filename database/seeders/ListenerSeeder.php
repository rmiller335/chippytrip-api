<?php

namespace Database\Seeders;

use App\Models\Airport;
use App\Models\Flight;
use App\Models\Listener;
use App\Models\User;
use App\Models\Watch;
use App\Services\FlightAwareSvc;
use App\Services\FlightLookupSvc;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use OwenIt\Auditing\Models\Audit;

// =============================================================================
class ListenerSeeder extends Seeder {
    protected FlightAwareSvc $fa;
	protected FlightLookupSvc $fl;

	// =========================================================================
	protected function addFlight($icao, $flightNo, $flight, $origin, $destination) {
		$date = Carbon::today()->addDay()->toDateString();

		$flightInfo = $this->fl->flight(
			$icao, $flightNo, $origin, $destination, str_replace('-', '', $date)
		);

		$departureDt =	Carbon::parse(
			$flightInfo->attributes->DepartureDateTime .
			$flightInfo->attributes->FLSDepartureTimeOffset
		)->utc();

		$arrivalDt =	Carbon::parse(
			$flightInfo->attributes->ArrivalDateTime .
			$flightInfo->attributes->FLSArrivalTimeOffset
		)->utc();

		$flight = new Flight([
			'airline_icao' =>		$icao,
			'flight_no' =>			$flightNo,
			'flight' =>				$flight,
			'origin_icao' =>		Airport::where('iata', $origin)->first()->icao,
			'destination_icao' =>	Airport::where('iata', $destination)->first()->icao,
			'departure_date' =>		$date,
			'departure_dt' =>		$departureDt,
			'arrival_dt' =>			$arrivalDt,
			'duration' =>			$departureDt->diffInMinutes($arrivalDt),
		]);

		$flight->save();

		Log::debug(json_encode($flight, JSON_PRETTY_PRINT));

		return $flight;
	}

	// =========================================================================
	protected function addWatch(Flight $flight) {
		$secret = bin2hex(random_bytes(16));

		$subsId = $this->fa->watchCreate($flight->flight, $flight->origin_icao,
			$flight->destination_icao, $flight->departure_date, $secret);

		if($subsId) {
			$watch = Watch::create([
				'flight_id' =>			$flight->id,
				'subscription_id' =>	$subsId,
			]);

			return $watch;
		}
		else {
			$this->command->error("Can't create flightaware watch!");
			exit(1);
		}
	}

	// =========================================================================
    public function run(FlightAwareSvc $fa, FlightLookupSvc $fl): void {
		$this->fa = $fa;
		$this->fl = $fl;

		if(config('app.debug')) {
			$user = User::where('email', 'rmiller@villamilla.com')->first();
			Auth::login($user);

			// Clean up old records
			$watches = $this->fa->watchList();
			Log::debug(json_encode($watches, JSON_PRETTY_PRINT));

			foreach($watches->alerts as $watch) {
				$id = $watch->id;

				$w = Watch::where('subscription_id', $id)->first();

				if($w) {
					$w->listeners()->delete();
					$w->callbacks()->delete();
					$w->delete();
				}

				$this->fa->watchDelete($id);
			}

			Flight::query()->delete();
			Audit::query()->delete();

			// Now for the new ones ...
			$flight = $this->addFlight(
				icao:			'DAL',
				flightNo:		185,
				flight:			'DAL185',
				origin:			'MXP',
				destination:	'JFK'
			);

			$watch = $this->addWatch($flight);
			$user->listeners()->create([
				'watch_id' =>	$watch->id,
				'travelers' =>	'Antonio & Silvia',
			]);

			$flight = $this->addFlight(
				icao:			'DAL',
				flightNo:		184,
				flight:			'DAL185',
				origin:			'JFK',
				destination:	'MXP'
			);

			$watch = $this->addWatch($flight);
			$user->listeners()->create([
				'watch_id' =>	$watch->id,
				'travelers' =>	'Giorgio & Chiara',
			]);

		}
		else {
			$this->command->error("Only in debug mode!");
		}
    }
}
