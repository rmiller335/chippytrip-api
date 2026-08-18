<?php

namespace App\Traits;

use App\Models\Watch;

// =============================================================================
trait Callback {
    // =========================================================================
	public function toArray(object $notifiable): array {
		$watch = Watch::where('subscription_id', $this->callback->alert_id)->first();

		return [
			'event' =>			$this->callback->event_code,
			'alert_id' =>		$this->callback->alert_id,
			'watch_id' =>		$watch ? $watch->id : null,
			'callback_id' =>	$this->callback->id,
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
			'title'				=> $this->callback->summary,
			'body' 				=> $this->callback->long_description,

			'actual_in'         => $this->callback->actual_in,
			'actual_off'        => $this->callback->actual_off,
			'actual_on'         => $this->callback->actual_on,
			'destination_city'  => $this->callback->destination_city,
			'destination_iata'  => $this->callback->destination_iata,
			'destination_icao'  => $this->callback->destination_icao,
			'destination_name'  => $this->callback->destination_name,
			'estimate_on'       => $this->callback->estimate_on,
			'estimated_in'      => $this->callback->estimated_in,
			'estimated_off'     => $this->callback->estimated_off,
			'event_code'        => $this->callback->event_code,
			'flight_number'     => $this->callback->flight_number,
			'ident_iata'        => $this->callback->ident_iata,
			'ident_icao'        => $this->callback->ident_icao,
			'origin_city'       => $this->callback->origin_city,
			'origin_iata'       => $this->callback->origin_iata,
			'origin_icao'       => $this->callback->origin_icao,
			'origin_name'       => $this->callback->origin_name,
			'scheduled_in'      => $this->callback->scheduled_in,
			'scheduled_off'     => $this->callback->scheduled_off,
			'scheduled_on'      => $this->callback->scheduled_on,
		];
    }
}
