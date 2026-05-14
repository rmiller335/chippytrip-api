<?php

namespace App\Models;

use App\Models\Airport;
use App\Models\Watch;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use OwenIt\Auditing\Contracts\Auditable;

// =============================================================================
class Flight extends Model implements Auditable {
	use \OwenIt\Auditing\Auditable;

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
	protected static function booted(): void {
		static::saving(function(Flight $flt) {
			$departure = new Carbon($flt->departure_date);
			$arrival = new Carbon($flt->arrival_dt);

			$flt->alert_start = $departure->subDay()->startOfDay();
			$flt->alert_end = $arrival->addDay()->endOfDay();
		});
	}

	// =========================================================================
	public function airline(): HasOne {
		return $this->hasOne(Airline::class, 'icao', 'airline_icao');
	}

	// =========================================================================
	public function destination(): HasOne {
		return $this->hasOne(Airport::class, 'icao', 'destination_icao');
	}

	// =========================================================================
	public function getAlertEndAttribute() {
		$dd = new Carbon($this->arrival_date);

		return $dd->addDay()->endOfDay();
	}

	// =========================================================================
	public function getAlertStartAttribute() {
		$dd = new Carbon($this->departure_date);

		return $dd->subDay()->startOfDay();
	}

	// =========================================================================
	public function origin(): HasOne {
		return $this->hasOne(Airport::class, 'icao', 'origin_icao');
	}

	// =========================================================================
	// Flights with the same flight number
	public function relatedFlights(): HasMany {
		return $this->hasMany(Flight::class, 'flight', 'flight');
	}

	// =========================================================================
	public function watch(): HasOne {
		return $this->hasOne(Watch::class, 'flight_id', 'id');
	}
}
