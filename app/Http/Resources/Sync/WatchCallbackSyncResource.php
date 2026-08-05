<?php

namespace App\Http\Resources\Sync;

use Illuminate\Http\Resources\Json\JsonResource;

// =============================================================================
class WatchCallbackSyncResource extends JsonResource {
    // =========================================================================
    public function toArray($request) {
        return [
            'id'                => $this->id,
            'flight_id'         => $this->flight_id,
            'alert_id'          => $this->alert_id,
            'event_code'        => $this->event_code,
            'summary'           => $this->summary,
            'short_description' => $this->short_description,
            'long_description'  => $this->long_description,
            'cancelled'         => $this->cancelled,
            'diverted'          => $this->diverted,

            'estimated_out' => $this->estimated_out,
            'actual_out'    => $this->actual_out,
            'estimated_off' => $this->estimated_off,
            'actual_off'    => $this->actual_off,
            'estimated_on'  => $this->estimated_on,
            'actual_on'     => $this->actual_on,
            'estimated_in'  => $this->estimated_in,
            'actual_in'     => $this->actual_in,

            'created_at' => $this->created_at,
        ];
    }
}
