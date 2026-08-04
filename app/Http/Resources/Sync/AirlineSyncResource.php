<?php

namespace App\Http\Resources\Sync;

use Illuminate\Http\Resources\Json\JsonResource;

// =============================================================================
class AirlineSyncResource extends JsonResource {
    // =========================================================================
    public function toArray($request) {
        return [
            'icao' => $this->icao,
            'iata' => $this->iata,
            'name' => $this->name,
        ];
    }
}
