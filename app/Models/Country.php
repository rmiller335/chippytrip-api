<?php

namespace App\Models;

use App\Models\Airline;
use App\Models\Airport;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

// =============================================================================
class Country extends Model implements Auditable {
	use \OwenIt\Auditing\Auditable;

	protected $fillable = [
		'iso2',
		'iso3',
		'name',
		'dominion',
	];

	// =========================================================================
	public function airlines(): HasMany {
		return $this->hasMany(Airline::class, 'country_code', 'iso2');
	}

	// =========================================================================
	public function airports(): HasMany {
		return $this->hasMany(Airport::class, 'country_code', 'iso2');
	}

	// =========================================================================
	public function equal(Country $rhs): bool {
		return 	   $this->iso2 == $rhs->iso2
				&& $this->iso3 == $rhs->iso3
				&& $this->name == $rhs->name
				&& $this->dominion == $rhs->dominion
		;
	}
}
