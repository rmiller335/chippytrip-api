<?php

namespace App\Models;

use App\Models\Airport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

// =============================================================================
class Flight extends Model {
	protected $casts = [
		'arrival_dt' =>		'datetime:Y-m-d H:i:s',
		'departure_dt' =>	'datetime:Y-m-d H:i:s',
	];

	protected $fillable = [
		'airline_icao',
		'arrival_dt',
		'departure_date',
		'departure_dt',
		'destination_icao',
		'duration',
		'flight',
		'flight_no',
		'origin_icao',
	];

	// =========================================================================
	public function airline(): HasOne {
		return $this->hasOne(Airline::class, 'icao', 'airline_icao');
	}

	// =========================================================================
	public function destination(): HasOne {
		return $this->hasOne(Airport::class, 'icao', 'destination_icao');
	}

	// =========================================================================
	public function origin(): HasOne {
		return $this->hasOne(Airport::class, 'icao', 'origin_icao');
	}
}
