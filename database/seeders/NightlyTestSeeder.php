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
class NightlyTestSeeder extends Seeder {
    protected FlightAwareSvc $fa;
	protected FlightLookupSvc $fl;

	// =========================================================================
	protected function addFlight($icao,
		$flightNo,
		$flight,
		$origin,
		$destination,
		$departure
	) {
		$flightInfo = $this->fl->flight(
			$icao, $flightNo, $origin, $destination, Carbon::now()->format('Ymd')
		);
Log::debug(json_encode($flightInfo, JSON_PRETTY_PRINT));

		$duration = Carbon::parse(
			$flightInfo->attributes->DepartureDateTime .
			$flightInfo->attributes->FLSDepartureTimeOffset
		)->diffInMinutes(Carbon::parse(
			$flightInfo->attributes->ArrivalDateTime .
			$flightInfo->attributes->FLSArrivalTimeOffset
		));

		$departureDt =	Carbon::parse(
			$departure->format('Y-m-d') .
			substr($flightInfo->attributes->DepartureDateTime, 10) .
			$flightInfo->attributes->FLSDepartureTimeOffset
		)->utc();

		$arrivalDt =	$departureDt->copy()->addMinutes($duration);

		$flight = new Flight([
			'airline_icao' =>		$icao,
			'flight_no' =>			$flightNo,
			'flight' =>				$flight,
			'origin_icao' =>		Airport::where('iata', $origin)->first()->icao,
			'destination_icao' =>	Airport::where('iata', $destination)->first()->icao,
			'departure_date' =>		$departure,
			'departure_dt' =>		$departureDt,
			'arrival_dt' =>			$arrivalDt,
			'duration' =>			$departureDt->diffInMinutes($arrivalDt),
		]);

		$flight->save();

//		Log::debug(json_encode($flight, JSON_PRETTY_PRINT));

		return $flight;
	}

	// =========================================================================
	protected function addWatch(Flight $flight, bool $enableWatch) {
		$secret = bin2hex(random_bytes(16));
		$start = $flight->alert_start->gt(Carbon::now()) ? $flight->alert_start : Carbon::now();

		$subsId = $this->fa->watchCreate($flight->flight, $flight->origin_icao,
			$flight->destination_icao, $start->format('Y-m-d'), $secret);

		if($subsId) {
			$watch = Watch::create([
				'flight_id' =>			$flight->id,
				'subscription_id' =>	$subsId,
				'secret' =>				$secret,
			]);

			if(!$enableWatch) {
				$this->fa->watchDelete($subsId);

				$watch->enabled = false;
				$watch->save();
			}

			return $watch;
		}
		else {
			$this->command->error("Can't create flightaware watch!");
			exit(1);
		}
	}

	// =========================================================================
	protected function createFlight(
		User	$user,
		string	$airline,
		string	$flightNo,
		string	$origin,
		string	$destination,
		Carbon	$date,
		string	$travellers,
		bool	$enableWatch
	){
		$flight = $this->addFlight(
			icao:			$airline,
			flightNo:		$flightNo,
			flight:			$airline . $flightNo,
			origin:			$origin,
			destination:	$destination,
			departure:		$date
		);

		$watch = $this->addWatch($flight, $enableWatch);

		$user->listeners()->create([
			'watch_id' =>	$watch->id,
			'travelers' =>	$travellers
		]);
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

			foreach($watches as $watch) {
				$id = $watch->id;

				$w = Watch::where('subscription_id', $id)->first();

				if($w) {
					$w->listeners()->delete();
					$w->callbacks()->delete();
					$w->delete();
				}

				$this->fa->watchDelete($id);
			}

			Listener::query()->delete();
			Watch::query()->delete();
			Flight::query()->delete();
			Audit::query()->delete();

			// Now for the new ones ...
			// In the past, with a watch
			$lastWeek = Carbon::now();
			$lastWeek->subWeek();

			$this->createFlight(
				user:			$user,
				airline:		'AAL',
				flightNo:		100,
				origin:			'JFK',
				destination:	'LHR',
				date:			$lastWeek,
				travellers:		'Anna & Fred',
				enableWatch:	true
			);

			// In the past, no watch
			$this->createFlight(
				user:			$user,
				airline:		'AAL',
				flightNo:		1006,
				origin:			'GUA',
				destination:	'MIA',
				date:			$lastWeek,
				travellers:		'Joan & Ernie',
				enableWatch:	false
			);

			// Current, with a watch
			$this->createFlight(
				user:			$user,
				airline:		'DAL',
				flightNo:		742,
				origin:			'JFK',
				destination:	'LAX',
				date:			Carbon::now(),
				travellers:		'Nicki & Andrew',
				enableWatch:	true
			);

			// Current, no watch
			$this->createFlight(
				user:			$user,
				airline:		'JBU',
				flightNo:		123,
				origin:			'JFK',
				destination:	'LAX',
				date:			Carbon::now(),
				travellers:		'Cindy & Charles',
				enableWatch:	false
			);

			// Future, with watch
			$nextWeek = Carbon::now();
			$nextWeek->addWeek();

			$this->createFlight(
				user:			$user,
				airline:		'BAW',
				flightNo:		1511,
				origin:			'JFK',
				destination:	'LHR',
				date:			$nextWeek,
				travellers:		'Laura & Evan',
				enableWatch:	true
			);

			// Future, no watch
			$this->createFlight(
				user:			$user,
				airline:		'AFR',
				flightNo:		006,
				origin:			'CDG',
				destination:	'JFK',
				date:			$nextWeek,
				travellers:		'Laura & Evan',
				enableWatch:	false
			);
		}
		else {
			$this->command->error("Only in debug mode!");
		}
    }
}
