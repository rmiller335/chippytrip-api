<?php

namespace App\Http\Resources\Sync;

use Illuminate\Http\Resources\Json\JsonResource;

// =============================================================================
class WatchSyncResource extends JsonResource {
    // =========================================================================
    public function toArray($request) {
        return [
            'id'              => $this->id,
            'flight_id'       => $this->flight_id,
            'subscription_id' => $this->subscription_id,
            'enabled'         => $this->enabled,
        ];
    }
}
