<?php

namespace App\Services;

use App\Models\Flight;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// =============================================================================
class FlightAwareSvc {
	private string $callback;
	private string $key;
	private string $url;

	// =========================================================================
	public function __construct() {
		$this->callback =	config('flightaware.callback');
		$this->key =		config('flightaware.key');
		$this->url =		config('flightaware.url');
	}

	// =========================================================================
	public function aircraftInfo(string $icao): ?object {
		$resp = Http::withHeaders([
			'x-apikey' =>	$this->key,
		])
		->get($this->url . '/aircraft/types/' . $icao);

		if($resp->successful()) {
			return arrayToObject($resp->json());
		}
		else {
			return null;
		}
	}

	// =========================================================================
	public function airportInfo(string $icao): object {
		$resp = Http::withHeaders([
			'x-apikey' =>	$this->key,
		])
		->get($this->url . '/airports/' . $icao);

		return arrayToObject($resp->json());
	}

	// =========================================================================
	public static function fixDT(?string $dt): ?string {
		if(null != $dt) {
			$dt = preg_replace('/T/', ' ', $dt);
			$dt = preg_replace('/:..Z$/', '', $dt);
		}

		return $dt;
	}

	// =========================================================================
	public function flightIdent(string $flight) {
		$info = $this->flightInfo($flight);

		return $info->flights[0]->ident_icao;
	}

	// =========================================================================
	public function flightInfo(string $ident, Carbon $start, Carbon $end) {
		$resp = Http::withHeaders([
			'x-apikey' =>	$this->key,
		])
		->withQueryParameters([
			'start' =>	$start->toIso8601String(),
			'end' =>	$end->toIso8601String(),
		])
		->get($this->url . '/flights/' . $ident);

		$r = arrayToObject($resp->json());
		$r->flights = collect($r->flights);

		return $r;
	}

	// =========================================================================
	public function flightSchedule(Flight $flight) {
		$start = $flight->departure_date->copy()->startOfDay();
		$end = $flight->departure_date->copy()->endOfDay();

		$url = implode('/', [
			$this->url,
			'schedules',
			$start->toIso8601ZuluString(),
			$end->toIso8601ZuluString()
		]);

		$resp = Http::withHeaders([
			'x-apikey' =>	$this->key,
		])
		->withQueryParameters([
			'airline' =>		$flight->airline_icao,
			'flight_number' =>	$flight->flight_no,
			'origin' =>			$flight->origin_icao,
			'destination' =>	$flight->destination_icao,
		])
		->get($url);

		$data = $resp->json();

		if(isset($data['scheduled'])) {
			foreach($data['scheduled'] as $entry) {
				if($entry['ident_iata'] == $flight->flight) {
					return arrayToObject($entry);
				}
			}
		}

		return null;
	}

	// =========================================================================
	public function flightsFromTo(string $from, string $to, bool $direct = false) {
		$url = $this->url . "/airports/$from/flights/to/$to";
		$options = $direct ? [
			'connection' =>	'nonstop',
		] : [
		]
		;

		$resp = Http::withHeaders([
			'x-apikey' =>	$this->key,
		])
		->get($url, $options);

		if($resp->successful()) {
			$r = arrayToObject($resp->json());
			$r->flights = collect($r->flights);

			return $r;
		}
		else {
			$resp->throw();
		}
	}

	// =========================================================================
	public function flightStatus(string $ident) {
		$today = Carbon::now();

		$tomorrow = Carbon::now();
		$tomorrow->addDay();

		$resp = Http::withHeaders([
			'x-apikey' =>	$this->key,
		])
		->get($this->url . '/flights/' . $ident, [
//			'start' =>	$today->format('Y-m-d'),
//			'end' =>	$tomorrow->format('Y-m-d'),
		]);

		$flight = $resp->json()['flights'][0];
		$flight = arrayToObject($flight);

		return $flight;
	}

	// =========================================================================
	public function operator(string $icao): object {
		$resp = Http::withHeaders([
			'x-apikey' =>	$this->key,
		])
		->get($this->url . '/operators/' . $icao);

		return arrayToObject($resp->json());
	}

	// =========================================================================
	protected function urlFor(string $secret): string {
		return url()->query($this->callback, [ 's' => $secret ]);
	}

	// =========================================================================
	public function watchById($watchId): ?object {
		$url = $this->url . '/alerts/' . $watchId;

		$resp = Http::withHeaders([
			'x-apikey' =>	$this->key,
		])->get($url);

		if($resp->successful()) {
			return $resp->object();
		}
		else {
			return null;
		}
	}

	// =========================================================================
	public function watchCreate(
		string $ident, string $org, string $dest, Carbon $startDate, $secret)
	{
		$target = url(config('flightaware.callback')) . '?s=' . $secret;

		$resp = Http::withOptions([ 'debug' => false ])
		->withHeaders([
			'x-apikey' =>	$this->key,
		])
		->withBody(json_encode([
			'ident' =>			$ident,
			'origin' =>			$org,
			'destination' =>	$dest,
			'start' =>			$startDate->format('Y-m-d'),
			'events' => [
				'arrival' =>	true,
				'cancelled' =>	true,
				'departure' =>	true,
				'diverted' =>	true,
				'filed' =>		true,
				'out' =>		true,
				'in' =>			true,
				'hold_start' =>	true,
				'hold_end' =>	true,
			],
			'target_url' =>		$target,
		]), 'application/json')
		->post($this->url . '/alerts');

		if($resp->created()) {
			$connection = $resp->header('Location');
			$connection = str_replace('/alerts/', '', $connection);
		}
		else {
			abort(500);
		}

		return $connection;
	}

	// =========================================================================
	public function watchDelete(string $watchId) {
		$url = $this->url . '/alerts/' . $watchId;

		$resp = Http::withHeaders([
			'x-apikey' =>	$this->key,
		])
			->delete($url)
		;
	}

	// =========================================================================
	public function watchInfo(string $watchId) {
		$resp = Http::withHeaders([
			'x-apikey' =>	$this->key,
		])
			->get($this->url . '/alerts/' . $watchId)
		;

		return arrayToObject($resp->json());
	}

	// =========================================================================
	public function watchList(): array {
		$url    = $this->url . '/alerts';
		$alerts = [];

		do {
			$resp = Http::withHeaders([
				'x-apikey' => $this->key,
			])->get($url);

			$body    = $resp->json();
			$alerts  = array_merge($alerts, $body['alerts'] ?? []);
			$nextUrl = $body['links']['next'] ?? null;
			$url     = $nextUrl ? $this->url . $nextUrl : null;

		} while ($url !== null);

		$alerts = array_map(fn($a) => arrayToObject($a), $alerts);

		// Ugly, but FA erroneously returns deleted alerts.
		$alerts = array_filter($alerts, function($a) {
			return null != $this->watchById($a->id);
		});

		return $alerts;
	}

	// =========================================================================
	public function watchUpdate(
		string $watchId,
		string $ident,
		string $org,
		string $dest,
		string $secret
	) {
		$watch = $this->watchInfo($watchId);

		$update = [
			'ident' =>			$ident,
			'origin' =>			$org,
			'destination' =>	$dest,
			'target_url' =>		$this->urlFor($secret),
			'events' =>			$watch->events,
		];

		$resp = Http::retry(5, 2000)
			->withHeaders([
				'x-apikey' =>	$this->key,
			])
			->withBody(json_encode($update))
			->put($this->url . '/alerts/' . $watchId)
		;
	}
}
