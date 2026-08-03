<?php

namespace App\Http\Resources\Sync;

use Illuminate\Http\Resources\Json\JsonResource;

class ListenerSyncResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'        => $this->id,
            'watch_id'  => $this->watch_id,
            'travelers' => $this->travelers,
        ];
    }
}
