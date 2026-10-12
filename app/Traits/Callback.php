<?php

namespace App\Traits;

// =============================================================================
trait Callback {
    // =========================================================================
	public function toArray(object $notifiable): array {
		return [
			'event' =>			$this->callback->event_code,
			'alert_id' =>		$this->callback->alert_id,
			'watch_id' =>		$this->callback->watch_id,
			'callback_id' =>	$this->callback->id,
			'title' =>			$this->callback->title,
			'body' =>			$this->callback->body,
			'summary' =>		$this->callback->summary,
			'description' =>	$this->callback->long_description,
		];
	}

    // =========================================================================
    public function via(object $notifiable): array {
		return array_unique(
			array_merge(
				$notifiable->channel_ids->toArray(), [ 'database' ]
			)
		);
    }

    // =========================================================================
    public function toFcm(object $notifiable): array {
		return [
			'title'				=> $this->callback->title,
			'body' 				=> $this->callback->body,
			'alert_id'			=> $this->callback->alert_id,

			'actual_in'         => $this->callback->actual_in?->toISOString(),
			'actual_off'        => $this->callback->actual_off?->toISOString(),
			'actual_on'         => $this->callback->actual_on?->toISOString(),
			'destination_city'  => $this->callback->destination_city,
			'destination_iata'  => $this->callback->destination_iata,
			'destination_icao'  => $this->callback->destination_icao,
			'destination_name'  => $this->callback->destination_name,
			'estimated_on'      => $this->callback->estimated_on?->toISOString(),
			'estimated_in'      => $this->callback->estimated_in?->toISOString(),
			'estimated_off'     => $this->callback->estimated_off?->toISOString(),
			'event_code'        => $this->callback->event_code,
			// The flight the user watched, as the sync resource sends it: for a
			// codeshare FlightAware reports the operator's (PT6126, not AA6126).
			'flight_number'     => $this->watchedFlight()?->flight ?? $this->callback->flight_number,
			'ident_iata'        => $this->watchedFlight()?->flight ?? $this->callback->ident_iata,
			'ident_icao'        => $this->watchedFlight()
				? $this->watchedFlight()->airline_icao . $this->watchedFlight()->flight_no
				: $this->callback->ident_icao,
			'origin_city'       => $this->callback->origin_city,
			'origin_iata'       => $this->callback->origin_iata,
			'origin_icao'       => $this->callback->origin_icao,
			'origin_name'       => $this->callback->origin_name,
			'scheduled_in'      => $this->callback->scheduled_in?->toISOString(),
			'scheduled_off'     => $this->callback->scheduled_off?->toISOString(),
			'scheduled_on'      => $this->callback->scheduled_on?->toISOString(),
		];
    }

    // =========================================================================
    private function watchedFlight(): ?\App\Models\Flight {
		return $this->callback->watch?->flight;
    }
}
