<?php

namespace App\Services;

use App\Models\Airline;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// =============================================================================
class AviationEdgeSvc {
	// =========================================================================
	public function airlines() {
		$url = $this->urlFor('/airlineDatabase');

		$response = Http::retry(5, 1000)
			->withOptions([ 'debug' => false ])
			->timeout(300)
			->get($url, [
				'key' =>			config('aviationedge.key'),
			])
		;

		return $response->json();
	}

	// =========================================================================
	public function fixRoutes(string $airline, array $routes): array {
		$airlineRec = Airline::where('icao', $airline)->first();

		foreach($routes as $i => $route) {
			if($route['airlineIcao']!= $airline) {
				$shares = $route['codeshares'];

				$shares[] = [
					'airline_code' =>	$route['airlineIata'],
					'flight_number' =>	$route['flightNumber'],
				];

				$goner = null;

				foreach($shares as $j => $share) {
					if($share['airline_code'] == $airlineRec->iata) {
						$route['airlineIata'] = $airlineRec->iata;
						$route['airlineIcao'] = $airlineRec->icao;
						$route['flightNumber'] = $share['flight_number'];

						$goner = $j;
					}
				}

				if(null != $goner) {
					array_splice($shares, $goner, 1, []);
				}

				$route['codeshares'] = $shares;
			}

			$route['fullFlightNo'] = $route['airlineIata'] . $route['flightNumber'];
			$routes[$i] = $route;
		}

		return $routes;
	}

	// =========================================================================
	public function flight(string $flightNum) {
		$url = $this->urlFor('/timetable');

		$iata = substr($flightNum, 0, 2);
		$flightNum = substr($flightNum, 2);

		$response = Http::retry(5, 1000)
			->withOptions([ 'debug' => false ])
			->get($url, [
				'key' =>			config('aviationedge.key'),
				'airline_iata' =>	$iata,
				'flight_num' =>		$flightNum,
			])
		;

		return $response->json()[0];
	}

	// =========================================================================
	public function futureFlight(
		string $iata, string $date, string $airlineIcao, string $flightNum
	) {
		Log::debug("futureFlight: iata = $iata");
		Log::debug("futureFlight: date = $date");
		Log::debug("futureFlight: airlineIcao = $airlineIcao");
		Log::debug("futureFlight: flightNum = $flightNum");

		$url = $this->urlFor('/flightsFuture');

		$response = Http::retry(3, 5000)
			->withOptions([ 'debug' => false ])
			->get($url, [
				'key' =>			config('aviationedge.key'),
				'iataCode' =>		$iata,
				'type' =>			'departure',
				'date' =>			$date,
				'airline_icao' =>	$airlineIcao,
				'flight_num' =>		$flightNum,
			])
		;

		return $response->json()[0];
	}

	// =========================================================================
	public function route(string $airline, string $origin, string $destination) {
		$url = $this->urlFor('/routes');

		$response = Http::retry(5, 1000)
			->withOptions([ 'debug' => false ])
			->get($url, [
				'key' =>			config('aviationedge.key'),
				'departureIcao' =>	$origin,
				'arrivalIcao' =>	$destination,
			])
		;

		$iata = Airline::where('icao', $airline)->first()->iata;

		$hits = $response->json();
		$flights = [];

		foreach($hits as $hit) {
			if($hit['airlineIcao'] == $airline) {
				$flights[] = $hit;
			}
			elseif(null != $hit['codeshares']) {
				foreach($hit['codeshares'] as $share) {
					if($iata == $share['airline_code']) {
						$flights[] = $hit;
					}
				}
			}
		}

		$flights = $this->fixRoutes($airline, $flights);

		return $flights;
	}

	// =========================================================================
	protected function urlFor(string $endpoint): string {
		return config('aviationedge.url') . $endpoint;
	}
}
