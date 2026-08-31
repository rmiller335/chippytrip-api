<?php

namespace App\Http\Resources\Sync;

use Illuminate\Http\Resources\Json\JsonResource;

// =============================================================================
class WatchCallbackSyncResource extends JsonResource {
	// =========================================================================
	public function toArray($request) {
		return [
			'id'				=> $this->id,
			'notification_id'	=> $this->notification_id,
			'flight_id'			=> $this->flight_id,
			'alert_id'			=> $this->alert_id,
			'event_code'		=> $this->event_code,
			'title'				=> $this->title,
			'body'				=> $this->body,
			'summary'			=> $this->summary,
			'short_description' => $this->short_description,
			'long_description'	=> $this->long_description,
			'cancelled'			=> $this->cancelled,
			'diverted'			=> $this->diverted,

			'flight_number'		=> $this->watch->flight->flight,
			'airline_icao'		=> $this->watch->flight->ident_icao,

			'origin_iata'		=> $this->watch->flight->origin?->iata,
			'origin_icao'		=> $this->watch->flight->origin?->icao,
			'origin_name'		=> $this->watch->flight->origin?->name,
			'origin_city'		=> $this->watch->flight->origin?->city,

			'destination_iata'	=> $this->watch->flight->destination?->iata,
			'destination_icao'	=> $this->watch->flight->destination?->icao,
			'destination_name'	=> $this->watch->flight->destination?->name,
			'destination_city'	=> $this->watch->flight->destination?->city,

			'scheduled_off'		=> $this->scheduled_off,
			'estimated_off'		=> $this->estimated_off,
			'actual_off'		=> $this->actual_off,

			'scheduled_on'		=> $this->scheduled_on,
			'estimated_on'		=> $this->estimated_on,
			'actual_on'			=> $this->actual_on,

			'scheduled_in'		=> $this->scheduled_in,
			'estimated_in'		=> $this->estimated_in,
			'actual_in'			=> $this->actual_in,

			'estimated_out'		=> $this->estimated_out,
			'actual_out'		=> $this->actual_out,

			'created_at'		=> $this->created_at,
		];
	}
}
