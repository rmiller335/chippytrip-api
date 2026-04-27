<?php

namespace App\Console\Commands;

use App\Models\Airport;
use App\Models\Flight;
use App\Services\AviationEdgeSvc;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

// =============================================================================
#[Signature('flights:add {flightno} {departure}')]
#[Description('Add a flight')]
class FlightsAdd extends Command {
	// =========================================================================
    public function handle(AviationEdgeSvc $ae) {
		$flightNo = $this->argument('flightno');
		$depDate = $this->argument('departure');

		Log::debug("flightNo = $flightNo");
		Log::debug("depDate = $depDate");

		$flightInfo = $ae->flight($flightNo);
		Log::debug(json_encode($flightInfo, JSON_PRETTY_PRINT));

		$futureInfo = $ae->futureFlight(
			$flightInfo['departure']['iataCode'],
			$depDate,
			$flightInfo['airline']['icaoCode'],
			$flightInfo['flight']['number']
		);
		Log::debug(json_encode($futureInfo, JSON_PRETTY_PRINT));
		$depAirport = Airport::where('icao', $futureInfo['departure']['icaoCode'])->first();
		$arrAirport = Airport::where('icao', $futureInfo['arrival']['icaoCode'])->first();

		$departureDt =	Carbon::parse(
			$depDate . ' ' . $futureInfo['departure']['scheduledTime'],
			$depAirport->timezone
		);

		$arrivalDt =	Carbon::parse(
			$depDate . ' ' . $futureInfo['arrival']['scheduledTime'],
			$arrAirport->timezone
		);

		if($arrivalDt->lessThan($departureDt)) {
			$arrivalDt = $arrivalDt->addDay();
		}

		$flight = new Flight([
			'airline_icao' =>		Str::upper($futureInfo['airline']['icaoCode']),
			'flight' =>				$flightNo,
			'flight_no' =>			Str::upper($futureInfo['flight']['number']),
			'origin_icao' =>		Str::upper($futureInfo['departure']['icaoCode']),
			'destination_icao' =>	Str::upper($futureInfo['arrival']['icaoCode']),
			'departure_date' =>		$depDate,
			'departure_dt' =>		$departureDt,
			'arrival_dt' =>			$arrivalDt,
			'duration' =>			$departureDt->diffInMinutes($arrivalDt)
		]);

		Log::debug(json_encode($flight, JSON_PRETTY_PRINT));

		$flight->save();
    }
}
