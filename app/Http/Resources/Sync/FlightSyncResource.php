<?php

namespace App\Http\Resources\Sync;

use Illuminate\Http\Resources\Json\JsonResource;

class FlightSyncResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'               => $this->id,
            'airline_icao'     => $this->airline_icao,
            'flight_no'        => $this->flight_no,
            'flight'           => $this->flight,
            'origin_icao'      => $this->origin_icao,
            'destination_icao' => $this->destination_icao,
            'departure_date'   => $this->departure_date,
            'departure_dt'     => $this->departure_dt,
            'arrival_dt'       => $this->arrival_dt,
        ];
    }
}
