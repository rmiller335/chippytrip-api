<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

		$resp = Http::withOptions([
			'debug' =>	false,
			'verify' => false,
		])
		->withHeaders([
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
}
