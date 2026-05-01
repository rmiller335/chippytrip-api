<?php

namespace App\Services;

use App\Models\SchedFlight;
use App\Models\SchedFlightLeg;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Kiwilan\XmlReader\XmlReader;

// =============================================================================
class FlightLookupSvc {
	// =========================================================================
	public function flight(
		string $airline, string $flightNo, string $origin, string $destination,
		string $date
	) {
		$url = implode('/', [
			config('flightlookup.url'),
			'TimeTable',
			$origin,
			$destination,
			$date
		]);

		$resp = Http::withHeaders([
			'x-rapidapi-host' =>	config('flightlookup.host'),
			'x-rapidapi-key' =>		config('flightlookup.key'),
		])->get($url, [
			'Connection' =>		'DIRECT',
			'FlightNumber' =>	$flightNo,
			'Airline' =>		$airline,
		]);

		$body = $resp->body();
		$r = xmlToObject($body);
		Log::debug(json_encode($r, JSON_PRETTY_PRINT));

		if(property_exists($r, 'FlightDetails')) {
			return $r->FlightDetails->FlightLegDetails;
		}
		else {
			return null;
		}
	}

	// =========================================================================
	public function schedule(
		string $origin, string $destination, string $date, string $conn = '1STOP'
	): Collection {
		$url = implode('/', [
			config('flightlookup.url'),
			'TimeTable',
			$origin,
			$destination,
			$date
		]);

		$cacheKey = implode('-', [
			'flight-lookup', $origin, $destination, $date, $conn
		]);

		if(config('flightsearch.cache-lookups')) {
			$body = Cache::remember($cacheKey, 86400, function() use($conn, $url) {
				$resp = Http::withHeaders([
					'x-rapidapi-host' =>	config('flightlookup.host'),
					'x-rapidapi-key' =>		config('flightlookup.key'),
				])->get($url, [
					'Connection' => $conn
				]);

				return $resp->body();
			});
		}
		else {
			$resp = Http::withHeaders([
				'x-rapidapi-host' =>	config('flightlookup.host'),
				'x-rapidapi-key' =>		config('flightlookup.key'),
			])->get($url, [
				'Connection' => $conn
			]);

			$body = $resp->body();
		}

		$xml = XmlReader::make($body);
		$data = $xml->getContents();
		Log::debug('FlightLookupSvc:');
		Log::debug(json_encode($data, JSON_PRETTY_PRINT));

		$flights = new Collection();

		if(array_key_exists('FlightDetails', $data)) {
			foreach($data['FlightDetails'] as $flight) {
				$flightObj = new SchedFlight([
					'destination' =>		$flight['@attributes']['FLSArrivalCode'],
					'num_legs' =>			$flight['@attributes']['FLSFlightLegs'],
					'origin' =>				$flight['@attributes']['FLSDepartureCode'],
					'total_flight_time' =>	$flight['@attributes']['TotalFlightTime'],
					'total_miles' =>		$flight['@attributes']['TotalMiles'],
					'total_trip_time' =>	$flight['@attributes']['TotalTripTime'],
				]);

				$flightObj->segments = new Collection();

				if(array_key_exists('@attributes', $flight['FlightLegDetails'])) {
					$legs = new Collection();
					$legs->push($flight['FlightLegDetails']);
				}
				else {
					$legs = collect($flight['FlightLegDetails']);
				}

				foreach($legs as $leg) {
					$legObj = new SchedFlightLeg([
						'arrival_dt' =>			$leg['@attributes']['ArrivalDateTime'],
						'arrival_offset' =>		$leg['@attributes']['FLSArrivalTimeOffset'],
						'departure_dt' =>		$leg['@attributes']['DepartureDateTime'],
						'departure_offset' =>	$leg['@attributes']['FLSDepartureTimeOffset'],
						'destination' =>		$leg['ArrivalAirport']['@attributes']['LocationCode'],
						'distance' =>			$leg['@attributes']['LegDistance'],
						'equipment' =>			$leg['Equipment']['@attributes']['AirEquipType'],
						'flight_no' =>			$leg['@attributes']['FlightNumber'],
						'flight_time' =>		$leg['@attributes']['JourneyDuration'],
						'mktg_airline' =>		$leg['MarketingAirline']['@attributes']['Code'],
						'origin' =>				$leg['DepartureAirport']['@attributes']['LocationCode'],
					]);

					$flightObj->segments->push($legObj);
				}

				$flights->push($flightObj);
			}
		}

		return $flights;
	}
}
