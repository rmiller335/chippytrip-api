<?php

namespace App\Models;

use App\Models\Country;
use App\Services\FlightAwareSvc;
use App\Services\OpenWeatherSvc;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Log;
use OwenIt\Auditing\Contracts\Auditable;

// =============================================================================
class Airport extends Model implements Auditable {
	use \OwenIt\Auditing\Auditable;

	protected $fillable = [
		'icao',
		'iata',
		'name',
		'city',
		'state',
		'longitude',
		'latitude',
		'timezone',
		'country_code',
		'wiki_url',
		'flights_url',
		'alternatives',
	];

	// =========================================================================
	public function country(): HasOne {
		return $this->hasOne(Country::class, 'iso2', 'country_code');
	}

	// =========================================================================
	public static function findOrFetch(string $icao): Airport {
		$airport = Airport::where('icao', $icao)->first();

		if($airport) {
			return $airport;
		}

		$fa = new FlightAwareSvc();
		$info = $fa->airportInfo($icao);

		$airport = Airport::create([
			'icao' =>			$info->code_icao,
			'iata' =>			$info->code_iata,
			'name' =>			$info->name,
			'elevation' =>		$info->elevation,
			'city' =>			$info->city,
			'state' =>			$info->state,
			'longitude' =>		$info->longitude,
			'latitude' =>		$info->latitude,
			'timezone' =>		$info->timezone,
			'country_code' =>	$info->country_code,
			'wiki_url' =>		$info->wiki_url,
			'flights_url' =>	$info->airport_flights_url,
			'alternatives' =>	$info->alternatives,
		]);

		return $airport;
	}

	// =========================================================================
	public static function icaoForIata(string $iata) {
		$ap = Airport::where('iata', $iata)->first();

		return $ap->icao;
	}

	// =========================================================================
	public function weatherAt(Carbon $dt): ?SpotForecast {
		$ow = new OpenWeatherSvc;

		$now = Carbon::now();

		if(8 < $now->diffInDays($dt)) {
			return null;
		}

		$forecast = $ow->forecast($this->latitude, $this->longitude);

		if(1 < $now->diffInDays($dt)) {
			foreach($forecast->daily as $daily) {
				if(1 < $dt->diffInDays($daily->dt)) {
					return $daily;
				}
			}
		}
		else {
			foreach($forecast->hourly as $hourly) {
				if(1 < $dt->diffInHours($hourly->dt)) {
					return $hourly;
				}
			}
		}

		return $forecast;
	}
}
