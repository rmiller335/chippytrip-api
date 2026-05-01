<?php

namespace App\Console\Commands;

use App\Models\Airline;
use App\Models\Airport;
use App\Models\Flight;
use App\Services\FlightLookupSvc;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

// =============================================================================
#[Signature('flights:add {flightno} {origin} {destination} {date}')]
#[Description('Add a flight')]
class FlightsAdd extends Command {
	// =========================================================================
    public function handle(FlightLookupSvc $fl) {
		$flightNo =		$this->argument('flightno');
		$origin =		$this->argument('origin');
		$destination =	$this->argument('destination');
		$date =			$this->argument('date');

		$icao = substr($flightNo, 0, 3);
		$flightNo = substr($flightNo, 3);

		$airline = Airline::where('icao', $icao)->first();
		null == $airline && $this->fail("Invalid airline icao: $icao");

		$flightInfo = $fl->flight(
			$icao, $flightNo, $origin, $destination, Str::replace('-', '', $date)
		);

		null == $flightInfo && $this->fail("No flights found");

		Log::debug(json_encode($flightInfo, JSON_PRETTY_PRINT));

		$depAirport = Airport::where(
			'iata', $flightInfo->DepartureAirport->attributes->LocationCode
		)->first();

		$arrAirport = Airport::where(
			'iata', $flightInfo->ArrivalAirport->attributes->LocationCode
		)->first();

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
			'flight' =>				$airline->iata . $flightNo,
			'flight_no' =>			$flightNo,
			'origin_icao' =>		$depAirport->icao,
			'destination_icao' =>	$arrAirport->icao,
			'departure_date' =>		$date,
			'departure_dt' =>		$departureDt,
			'arrival_dt' =>			$arrivalDt,
			'duration' =>			$departureDt->diffInMinutes($arrivalDt)
		]);

		$flight->save();
    }
}
