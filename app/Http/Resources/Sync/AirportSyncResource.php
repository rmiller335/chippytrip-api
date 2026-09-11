<?php

namespace App\Http\Resources\Sync;

use Illuminate\Http\Resources\Json\JsonResource;

// =============================================================================
class AirportSyncResource extends JsonResource {
    // =========================================================================
    public function toArray($request) {
        return [
            'icao' =>			$this->icao,
            'iata' =>			$this->iata,
            'name' =>			$this->name,
			'display_name' =>	$this->display_name,
            'city' =>			$this->city,
			'timezone' =>		$this->timezone,
        ];
    }
}
