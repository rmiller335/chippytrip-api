<?php

namespace App\Models;

use App\Models\Country;
use App\Services\FlightAwareSvc;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Log;
use OwenIt\Auditing\Contracts\Auditable;

// =============================================================================
class Airline extends Model implements Auditable {
	use \OwenIt\Auditing\Auditable;

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
		$lhsTypes = explode(',', $this->types);
		$rhsTypes = explode(',', $rhs->types);

		sort($lhsTypes);
		sort($rhsTypes);

		return $lhsTypes == $rhsTypes;
	}
}
