<?php

namespace App\Models;

use App\Models\Airport;
use App\Models\Watch;
use App\Services\FlightAwareSvc;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Log;

// =============================================================================
class Flight extends Model {
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
		'operating_ident',
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

				$flt->duration = (int) $dep->diffInMinutes($arr);
			}
		});
	}

	// =========================================================================
	// [start, end] dates for the AeroAPI alert: the departure date (origin-local)
	// widened by flightaware.alert_buffer_days either side.
	public function alertWindow(): array {
		$date = Carbon::parse($this->departure_date->toDateString());
		$buffer = max(0, (int) config('flightaware.alert_buffer_days', 1));

		return [$date->copy()->subDays($buffer), $date->copy()->addDays($buffer)];
	}

	// =========================================================================
	// The ident to put on the AeroAPI alert: the operating flight's, looked up
	// from the schedule (and kept) if we don't have it yet. Falls back to the
	// marketing flight number, which only works when it isn't a codeshare.
	public function alertIdent(FlightAwareSvc $fa): string {
		if (! $this->operating_ident) {
			try {
				$info = $fa->flightSchedule($this);
			}
			catch (\Throwable $e) {
				Log::warning("Flight {$this->id}: schedule lookup for the alert ident failed: {$e->getMessage()}");
				$info = null;
			}

			if ($info && ($ident = FlightAwareSvc::operatingIdent($info))) {
				$this->operating_ident = $ident;
				$this->save();
			}
		}

		return $this->operating_ident ?: $this->flight;
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
