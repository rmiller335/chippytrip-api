<?php

namespace App\Models;

use App\Models\Country;
use App\Services\FlightAwareSvc;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use OwenIt\Auditing\Contracts\Auditable;

// =============================================================================
class Airport extends Model implements Auditable {
	use \OwenIt\Auditing\Auditable;

	protected $casts = [
		'alternatives' =>	'array',
	];

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
		'elevation',
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
}
