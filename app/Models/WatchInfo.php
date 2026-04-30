<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/*
 * ---------------------------------------------------------------------------
 * Event codes (event_code field)
 * ---------------------------------------------------------------------------
 *  filed        – flight plan filed
 *  offblock     – aircraft left the gate (actual_out)
 *  departure    – wheels off (actual_off)
 *  arrival      – wheels on (actual_on)
 *  onblock      – aircraft at gate (actual_in)
 *  diverted     – flight diverted to alternate airport
 *  cancelled    – flight cancelled
 *  departure_delay  – significant departure delay (>30 min)
 *  arrival_delay    – significant en-route delay (>30 min)
 *  gate_change  – departure or arrival gate changed
 *  airport_delay    – general airport-level delay
*/

// =============================================================================
class WatchInfo extends Model {
	protected $table = 'watch_callbacks';

	protected $fillable = [
        'alert_id',
        'event_code',
        'summary',
        'short_description',
        'long_description',
 
        'fa_flight_id',
        'ident',
        'ident_icao',
        'ident_iata',
        'registration',
        'atc_ident',
        'inbound_fa_flight_id',
 
        'aircraft_type',
 
        'origin',
        'origin_icao',
        'origin_iata',
        'origin_name',
        'origin_city',
        'destination',
        'destination_icao',
        'destination_iata',
        'destination_name',
        'destination_city',
        'route',
        'route_distance',
        'filed_ete',
        'filed_altitude',
        'filed_airspeed_kts',
 
        'gate_origin',
        'gate_destination',
        'terminal_origin',
        'terminal_destination',
        'baggage_claim',
 
        'operator',
        'operator_icao',
        'operator_iata',
        'flight_number',
 
        'scheduled_out',
        'estimated_out',
        'actual_out',
        'scheduled_off',
        'estimated_off',
        'actual_off',
        'scheduled_on',
        'estimated_on',
        'actual_on',
        'scheduled_in',
        'estimated_in',
        'actual_in',
 
        'position_only',
        'blocked',
        'cancelled',
        'diverted',
 
        'flight_error',
        'raw_payload',
        'source_ip',
    ];
 
    protected $casts = [
        // Timestamps
        'scheduled_out' => 'datetime',
        'estimated_out' => 'datetime',
        'actual_out'    => 'datetime',
        'scheduled_off' => 'datetime',
        'estimated_off' => 'datetime',
        'actual_off'    => 'datetime',
        'scheduled_on'  => 'datetime',
        'estimated_on'  => 'datetime',
        'actual_on'     => 'datetime',
        'scheduled_in'  => 'datetime',
        'estimated_in'  => 'datetime',
        'actual_in'     => 'datetime',
 
        // Booleans
        'position_only' => 'boolean',
        'blocked'       => 'boolean',
        'cancelled'     => 'boolean',
        'diverted'      => 'boolean',
 
        // JSON
        'raw_payload'   => 'array',
    ];
 
    // -------------------------------------------------------------------------
    // Factory / hydration
    // -------------------------------------------------------------------------
 
    /**
     * Build a new (unsaved) model instance from an AeroAPI v4 callback payload.
     *
     * @param  array       $payload  Decoded JSON body from FlightAware
     * @param  string|null $sourceIp Source IP of the incoming request
     */
    public static function fromApiPayload(array $payload, ?string $sourceIp = null): static
    {
        $flight = $payload['flight'] ?? [];
        $origin = $flight['origin'] ?? [];
        $dest   = $flight['destination'] ?? [];
 
        return new static([
            // Envelope
            'alert_id'          => $payload['alert_id'] ?? null,
            'event_code'        => $payload['event_code'] ?? null,
            'summary'           => $payload['summary'] ?? null,
            'short_description' => $payload['short_description'] ?? null,
            'long_description'  => $payload['long_description'] ?? null,
 
            // Flight identity
            'fa_flight_id'          => $flight['fa_flight_id'] ?? null,
            'ident'                 => $flight['ident'] ?? null,
            'ident_icao'            => $flight['ident_icao'] ?? null,
            'ident_iata'            => $flight['ident_iata'] ?? null,
            'registration'          => $flight['registration'] ?? null,
            'atc_ident'             => $flight['atc_ident'] ?? null,
            'inbound_fa_flight_id'  => $flight['inbound_fa_flight_id'] ?? null,
 
            // Aircraft
            'aircraft_type' => $flight['aircraft_type'] ?? null,
 
            // Origin airport
            'origin'       => is_array($origin) ? ($origin['code'] ?? null) : $origin,
            'origin_icao'  => is_array($origin) ? ($origin['code_icao'] ?? null) : null,
            'origin_iata'  => is_array($origin) ? ($origin['code_iata'] ?? null) : null,
            'origin_name'  => is_array($origin) ? ($origin['name'] ?? null) : null,
            'origin_city'  => is_array($origin) ? ($origin['city'] ?? null) : null,
 
            // Destination airport
            'destination'       => is_array($dest) ? ($dest['code'] ?? null) : $dest,
            'destination_icao'  => is_array($dest) ? ($dest['code_icao'] ?? null) : null,
            'destination_iata'  => is_array($dest) ? ($dest['code_iata'] ?? null) : null,
            'destination_name'  => is_array($dest) ? ($dest['name'] ?? null) : null,
            'destination_city'  => is_array($dest) ? ($dest['city'] ?? null) : null,
 
            // Route / plan
            'route'              => $flight['route'] ?? null,
            'route_distance'     => $flight['route_distance'] ?? null,
            'filed_ete'          => $flight['filed_ete'] ?? null,
            'filed_altitude'     => $flight['filed_altitude'] ?? null,
            'filed_airspeed_kts' => $flight['filed_airspeed_kts'] ?? null,
 
            // Gate / terminal
            'gate_origin'           => $flight['gate_origin'] ?? null,
            'gate_destination'      => $flight['gate_destination'] ?? null,
            'terminal_origin'       => $flight['terminal_origin'] ?? null,
            'terminal_destination'  => $flight['terminal_destination'] ?? null,
            'baggage_claim'         => $flight['baggage_claim'] ?? null,
 
            // Operator
            'operator'      => $flight['operator'] ?? null,
            'operator_icao' => $flight['operator_icao'] ?? null,
            'operator_iata' => $flight['operator_iata'] ?? null,
            'flight_number' => $flight['flight_number'] ?? null,
 
            // Timestamps
            'scheduled_out' => $flight['scheduled_out'] ?? null,
            'estimated_out' => $flight['estimated_out'] ?? null,
            'actual_out'    => $flight['actual_out'] ?? null,
            'scheduled_off' => $flight['scheduled_off'] ?? null,
            'estimated_off' => $flight['estimated_off'] ?? null,
            'actual_off'    => $flight['actual_off'] ?? null,
            'scheduled_on'  => $flight['scheduled_on'] ?? null,
            'estimated_on'  => $flight['estimated_on'] ?? null,
            'actual_on'     => $flight['actual_on'] ?? null,
            'scheduled_in'  => $flight['scheduled_in'] ?? null,
            'estimated_in'  => $flight['estimated_in'] ?? null,
            'actual_in'     => $flight['actual_in'] ?? null,
 
            // Flags
            'position_only' => $flight['position_only'] ?? false,
            'blocked'       => $flight['blocked'] ?? false,
            'cancelled'     => $flight['cancelled'] ?? false,
            'diverted'      => $flight['diverted'] ?? false,
 
            // Error + raw
            'flight_error' => $flight['error'] ?? null,
            'raw_payload'  => $payload,
            'source_ip'    => $sourceIp,
        ]);
    }
 
    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------
 
    /**
     * Best-effort actual departure time: actual_off (wheels up) falling back
     * to actual_out (gate departure).
     */
    public function getDepartedAtAttribute(): ?\Carbon\Carbon
    {
        return $this->actual_off ?? $this->actual_out;
    }
 
    /**
     * Best-effort actual arrival time: actual_on (wheels down) falling back
     * to actual_in (gate arrival).
     */
    public function getArrivedAtAttribute(): ?\Carbon\Carbon
    {
        return $this->actual_on ?? $this->actual_in;
    }
 
    /**
     * Computed block time in minutes (actual_in – actual_out).
     * Returns null if either timestamp is missing.
     */
    public function getBlockMinutesAttribute(): ?int
    {
        if ($this->actual_out && $this->actual_in) {
            return (int) $this->actual_out->diffInMinutes($this->actual_in);
        }
 
        return null;
    }
 
    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------
 
    /** Filter by FlightAware flight ID. */
    public function scopeForFlight(Builder $query, string $faFlightId): Builder
    {
        return $query->where('fa_flight_id', $faFlightId);
    }
 
    /** Filter by callsign / ident. */
    public function scopeForIdent(Builder $query, string $ident): Builder
    {
        return $query->where('ident', $ident);
    }
 
    /** Filter by event code (e.g. 'departure', 'arrival'). */
    public function scopeForEvent(Builder $query, string $eventCode): Builder
    {
        return $query->where('event_code', $eventCode);
    }
 
    /** Filter by origin airport code. */
    public function scopeFromOrigin(Builder $query, string $airport): Builder
    {
        return $query->where('origin', strtoupper($airport));
    }
 
    /** Filter by destination airport code. */
    public function scopeToDestination(Builder $query, string $airport): Builder
    {
        return $query->where('destination', strtoupper($airport));
    }
 
    /** Only cancelled flights. */
    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('cancelled', true);
    }
 
    /** Only diverted flights. */
    public function scopeDiverted(Builder $query): Builder
    {
        return $query->where('diverted', true);
    }
 
    /** Callbacks received within the last N hours. */
    public function scopeRecent(Builder $query, int $hours = 24): Builder
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }
}
