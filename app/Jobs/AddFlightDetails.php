<?php

namespace App\Jobs;

use App\Models\Flight;
use App\Services\FlightAwareSvc;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

// =============================================================================
class AddFlightDetails implements ShouldQueue {
    use Queueable;

	// =========================================================================
    public function __construct(private readonly Flight $flight) {
    }

	// =========================================================================
    public function handle(FlightAwareSvc $fa): void {
		$info = $fa->flightSchedule($this->flight);

		if(null != $info) {
			$this->flight->departure_dt =	new Carbon($info->scheduled_out);
			$this->flight->arrival_dt =		new Carbon($info->scheduled_in);
			$this->flight->equipment =		$info->aircraft_type;
			$this->flight->meal_service =	$info->meal_service;
			$this->flight->first_seats =	$info->seats_cabin_first;
			$this->flight->business_seats =	$info->seats_cabin_business;
			$this->flight->coach_seats =	$info->seats_cabin_coach;

			$this->flight->update();

			return;
		}

		if($this->flight->departure_date->startOfDay() == Carbon::now()->startOfDay()) {
			$start = $this->flight->departure_date->copy()->startOfDay();
			$end = $this->flight->departure_date->copy()->endOfDay();

			$info = $fa->flightInfo($this->flight->flight, $start, $end);
			$match = $info->flights->firstWhere('ident_iata', $this->flight->flight);

			if(null != $match) {
				$this->flight->departure_dt =	new Carbon($match->scheduled_out);
				$this->flight->arrival_dt =		new Carbon($match->scheduled_in);
				$this->flight->equipment =		$match->aircraft_type;
				$this->flight->meal_service =	$match->meal_service;
				$this->flight->first_seats =	$match->seats_cabin_first;
				$this->flight->business_seats =	$match->seats_cabin_business;
				$this->flight->coach_seats =	$match->seats_cabin_coach;

				$this->flight->update();

				return;
			}
		}

		$this->fail("No match for flight id = " . $this->flight->id);
    }
}
