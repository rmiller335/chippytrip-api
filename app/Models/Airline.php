<?php

namespace App\Models;

use App\Models\Country;
use App\Services\FlightAwareSvc;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Log;

// =============================================================================
class Airline extends Model {
	protected $casts = [
		'types' =>	'array',
	];

	protected $fillable = [
		'icao',
		'iata',
		'call_sign',
		'name',
		'country_code',
		'status',
		'types',
	];

	// =========================================================================
	public function country(): HasOne {
		return $this->hasOne(Country::class, 'iso2', 'country_code');
	}

	// =========================================================================
	public function equal(Airline $rhs) {
		$r =	   $this->icao == $rhs->icao
				&& $this->iata == $rhs->iata
				&& $this->call_sign == $rhs->call_sign
				&& $this->name == $rhs->name
				&& $this->country_code == $rhs->country_code
				&& $this->status == $rhs->status
				&& $this->sameTypes($rhs)
		;

		return $r;
	}

	// =========================================================================
	public function hasType(string $type): bool {
		Log::debug(json_encode($this->types, JSON_PRETTY_PRINT));

		return in_array($type, $this->types);
	}

	// =========================================================================
	protected function sameTypes(Airline $rhs) {
		$lhsTypes = $this->types;
		$rhsTypes = $rhs->types;

		sort($lhsTypes);
		sort($rhsTypes);

		return $lhsTypes == $rhsTypes;
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

		foreach (static::all() as $airline) {
			$candidate = static::normalizeName($airline->name);

			if (! $candidate) {
				continue;
			}

			if ($candidate === $needle) {
				return $airline;
			}

			$score = static::similarityScore(
				$needle,
				$candidate
			);

			if ($score > $bestScore) {
				$bestScore = $score;
				$best = $airline;
			}
		}

		return $bestScore >= 75
			? $best
			: null;
	}

	// =========================================================================
	private static function normalizeName(string $name): string {
		$name = mb_strtolower(trim($name));

		// Normalize Unicode where intl is available.
		if (class_exists(\Normalizer::class)) {
			$name = \Normalizer::normalize(
				$name,
				\Normalizer::FORM_KC
			);
		}

		/*
		 * When a legal/operator name is followed by a trading name,
		 * prefer the legal/operator portion.
		 *
		 * Examples:
		 *   Endeavor Air DBA Delta Connection
		 *       -> Endeavor Air
		 *
		 *   XYZ Airlines trading as Foo Air
		 *       -> XYZ Airlines
		 */
		$name = preg_split(
			'/\b(?:dba|d\/b\/a|doing business as|trading as|t\/a)\b/ui',
			$name,
			2
		)[0];

		// Remove common legal entity suffixes internationally.
		$name = preg_replace(
			'/\b(?:' .
				'inc(?:orporated)?|' .
				'corp(?:oration)?|' .
				'llc|' .
				'ltd|' .
				'limited|' .
				'plc|' .
				'co(?:mpany)?|' .
				'gmbh|' .
				'ag|' .
				'sa|' .
				's\.a\.|' .
				'sas|' .
				'sarl|' .
				's\.r\.l\.|' .
				'srl|' .
				'spa|' .
				's\.p\.a\.|' .
				'bv|' .
				'nv|' .
				'oy|' .
				'oyj|' .
				'ab|' .
				'as|' .
				'asa|' .
				'a\/s|' .
				'pte|' .
				'pty|' .
				'bhd|' .
				'sdn|' .
				'berhad' .
			')\b\.?/ui',
			' ',
			$name
		);

		// Remove punctuation but retain letters/numbers from all scripts.
		$name = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $name);

		// Collapse whitespace.
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
