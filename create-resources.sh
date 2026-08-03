#!/usr/bin/env bash
#
set -euo pipefail

# Run this from the root of chippytrip-api (where artisan lives)
# Creates app/Http/Resources/Sync/*.php with toArray() already filled in

DIR="app/Http/Resources/Sync"
mkdir -p "${DIR}"

cat > "${DIR}/ListenerSyncResource.php" <<'PHP'
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
PHP

cat > "${DIR}/WatchSyncResource.php" <<'PHP'
<?php

namespace App\Http\Resources\Sync;

use Illuminate\Http\Resources\Json\JsonResource;

class WatchSyncResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'              => $this->id,
            'flight_id'       => $this->flight_id,
            'subscription_id' => $this->subscription_id,
            'enabled'         => $this->enabled,
        ];
    }
}
PHP

cat > "${DIR}/FlightSyncResource.php" <<'PHP'
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
PHP

cat > "${DIR}/AirlineSyncResource.php" <<'PHP'
<?php

namespace App\Http\Resources\Sync;

use Illuminate\Http\Resources\Json\JsonResource;

class AirlineSyncResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'icao' => $this->icao,
            'iata' => $this->iata,
            'name' => $this->name,
        ];
    }
}
PHP

cat > "${DIR}/AirportSyncResource.php" <<'PHP'
<?php

namespace App\Http\Resources\Sync;

use Illuminate\Http\Resources\Json\JsonResource;

class AirportSyncResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'icao' => $this->icao,
            'iata' => $this->iata,
            'name' => $this->name,
            'city' => $this->city,
        ];
    }
}
PHP

echo "Done. Resources written to ${DIR}/"
