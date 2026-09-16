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
		'display_name',
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
	public static function findBestMatch(
		?string $name,
		?string $iata,
		?string $icao,
	): ?self {
		if ($iata) {
			$match = static::where('iata', strtoupper($iata))->first();

			if ($match) {
				return $match;
			}
		}

		if ($icao) {
			$match = static::where('icao', strtoupper($icao))->first();

			if ($match) {
				return $match;
			}
		}

		if (! $name) {
			return null;
		}

		$match = static::whereRaw(
			'LOWER(name) = ?',
			[mb_strtolower($name)]
		)->first();

		if ($match) {
			return $match;
		}

		return static::findFuzzyNameMatch($name);
	}

	// =========================================================================
	private static function findFuzzyNameMatch(string $name): ?self {
		$needle = static::normalizeName($name);

		if (! $needle) {
			return null;
		}

		$best = null;
		$bestScore = 0;

		foreach (static::all() as $airport) {
			$candidate = static::normalizeName($airport->name);

			if (! $candidate) {
				continue;
			}

			if ($candidate === $needle) {
				return $airport;
			}

			$score = static::similarityScore(
				$needle,
				$candidate
			);

			if ($score > $bestScore) {
				$bestScore = $score;
				$best = $airport;
			}
		}

		return $bestScore >= 75
			? $best
			: null;
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
	public static function getAirportName(string $icao, string $iata): string {
		$airport = Airport::where('icao', $icao)
			->orWhere('iata', $iata)
			->first();

		if ($airport) {
			return $airport->name;
		}

		return '';
	}

	// =========================================================================
	public static function icaoForIata(string $iata) {
		$ap = Airport::where('iata', $iata)->first();

		return $ap->icao;
	}

	// =========================================================================
	private static function normalizeName(string $name): string {
		$name = mb_strtolower(trim($name));

		if (class_exists(\Normalizer::class)) {
			$name = \Normalizer::normalize(
				$name,
				\Normalizer::FORM_KC
			);
		}

		/*
		 * Normalize common abbreviations before removing generic
		 * airport terminology.
		 */
		$name = preg_replace(
			'/\b(?:intl|int\'l|int\.)\b/ui',
			'international',
			$name
		);

		$name = preg_replace(
			'/\bapt\b/ui',
			'airport',
			$name
		);

		/*
		 * Remove generic airport/facility terminology.
		 *
		 * Keep words such as greater, metropolitan, regional and
		 * municipal because they may distinguish one airport from another.
		 */
		$name = preg_replace(
			'/\b(?:' .
				'international airport|' .
				'international aerodrome|' .
				'airport|' .
				'aerodrome|' .
				'airfield|' .
				'air terminal|' .
				'aeroport|' .
				'aéroport|' .
				'aeropuerto|' .
				'aeroporto|' .
				'aeroportu|' .
				'flughafen|' .
				'flugplatz|' .
				'luchthaven|' .
				'lufthavn|' .
				'flyplass|' .
				'flygplats|' .
				'lentoasema|' .
				'letiste|' .
				'letiště|' .
				'lotnisko|' .
				'havalimani|' .
				'havalimanı|' .
				'aerodrom|' .
				'aerodromo|' .
				'aeródromo' .
			')\b/ui',
			' ',
			$name
		);

		$name = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $name);
		$name = preg_replace('/\s+/u', ' ', $name);

		return trim($name);
	}

	// =========================================================================
	private static function similarityScore(
		string $needle,
		string $candidate
	): float {
		similar_text(
			$needle,
			$candidate,
			$characterScore
		);

		$needleTokens = static::tokens($needle);
		$candidateTokens = static::tokens($candidate);

		if (! count($needleTokens) || ! count($candidateTokens)) {
			return 0;
		}

		$matched = 0;

		foreach ($needleTokens as $needleToken) {
			$bestTokenScore = 0;

			foreach ($candidateTokens as $candidateToken) {
				if ($needleToken === $candidateToken) {
					$bestTokenScore = 100;
					break;
				}

				similar_text(
					$needleToken,
					$candidateToken,
					$tokenScore
				);

				$bestTokenScore = max(
					$bestTokenScore,
					$tokenScore
				);
			}

			if ($bestTokenScore >= 75) {
				$matched += $bestTokenScore / 100;
			}
		}

		$coverageScore = 100 * ($matched / count($needleTokens));

		return
			($coverageScore * 0.70) +
			($characterScore * 0.30);
	}

	// =========================================================================
	private static function tokens(string $name): array {
		return array_values(
			array_filter(
				preg_split('/\s+/u', $name)
			)
		);
	}

}
