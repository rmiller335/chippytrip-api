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
		'alert_end' =>		'date',
		'alert_start' =>	'date',
		'departure_date' =>	'datetime',
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

			$flt->alert_start = $departure->copy()->subDay()->startOfDay();
			$flt->alert_end = $departure->copy()->addDays(2)->endOfDay();

			if($flt->departure_dt && $flt->arrival_dt) {
				$dep = new Carbon($flt->departure_dt);
				$arr = new Carbon($flt->arrival_dt);

				$flt->duration = $dep->diffInMinutes($arr);
			}
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
	public static function icaoFromFlightNum(string $flightNo): string {
		$iata = substr($flightNo, 0, 2);
		$airline = Airline::where('iata', $iata)->first();

		return $airline->icao;
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
