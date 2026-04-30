<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// =============================================================================
return new class extends Migration {
	// =========================================================================
    public function up(): void {
        Schema::create('watch_callbacks', function (Blueprint $table) {
            $table->id();

            // ---------------------------------------------------------------------------
            // Top-level alert envelope fields
            // ---------------------------------------------------------------------------
            $table->unsignedBigInteger('alert_id')->index()
                ->comment('FlightAware alert ID that triggered this callback');
 
            $table->string('event_code', 50)->index()
                ->comment('Event type: filed, offblock, departure, arrival, onblock, diverted, cancelled, ...');
 
            $table->string('summary')->nullable()
                ->comment('Short one-line human-readable summary of the event');
 
            $table->string('short_description')->nullable()
                ->comment('Brief description of the event');
 
            $table->text('long_description')->nullable()
                ->comment('Full human-readable description of the event');
 
            // ---------------------------------------------------------------------------
            // Core flight identity
            // ---------------------------------------------------------------------------
            $table->string('fa_flight_id', 100)->index()
                ->comment('FlightAware unique flight identifier, e.g. UAL123-1234567890-airline-0123');
 
            $table->string('ident', 20)->index()
                ->comment('Flight identifier / callsign, e.g. UAL123');
 
            $table->string('ident_icao', 20)->nullable()
                ->comment('ICAO operator + flight number, e.g. UAL123');
 
            $table->string('ident_iata', 20)->nullable()
                ->comment('IATA operator + flight number, e.g. UA123');
 
            $table->string('registration', 20)->nullable()->index()
                ->comment('Aircraft tail/registration number, e.g. N12345');
 
            $table->string('atc_ident', 20)->nullable()
                ->comment('ATC identifier used for the flight');
 
            $table->string('inbound_fa_flight_id', 100)->nullable()
                ->comment('fa_flight_id of the inbound (previous) leg');
 
            // ---------------------------------------------------------------------------
            // Aircraft
            // ---------------------------------------------------------------------------
            $table->string('aircraft_type', 10)->nullable()
                ->comment('ICAO (or IATA) aircraft type code, e.g. B738');
 
            // ---------------------------------------------------------------------------
            // Route / plan
            // ---------------------------------------------------------------------------
            $table->string('origin', 10)->nullable()->index()
                ->comment('Origin airport ICAO/IATA/LID code');
 
            $table->string('origin_icao', 10)->nullable();
            $table->string('origin_iata', 10)->nullable();
            $table->string('origin_name')->nullable();
            $table->string('origin_city')->nullable();
 
            $table->string('destination', 10)->nullable()->index()
                ->comment('Destination airport ICAO/IATA/LID code');
 
            $table->string('destination_icao', 10)->nullable();
            $table->string('destination_iata', 10)->nullable();
            $table->string('destination_name')->nullable();
            $table->string('destination_city')->nullable();
 
            $table->text('route')->nullable()
                ->comment('Filed route string');
 
            $table->unsignedInteger('route_distance')->nullable()
                ->comment('Filed route distance in nautical miles');
 
            $table->unsignedInteger('filed_ete')->nullable()
                ->comment('Filed estimated time en-route in seconds');
 
            $table->unsignedInteger('filed_altitude')->nullable()
                ->comment('Filed cruising altitude in hundreds of feet (FL)');
 
            $table->unsignedInteger('filed_airspeed_kts')->nullable()
                ->comment('Filed true airspeed in knots');
 
            // ---------------------------------------------------------------------------
            // Gate / terminal
            // ---------------------------------------------------------------------------
            $table->string('gate_origin', 20)->nullable();
            $table->string('gate_destination', 20)->nullable();
            $table->string('terminal_origin', 20)->nullable();
            $table->string('terminal_destination', 20)->nullable();
            $table->string('baggage_claim', 20)->nullable();
 
            // ---------------------------------------------------------------------------
            // Operator / airline
            // ---------------------------------------------------------------------------
            $table->string('operator', 20)->nullable()
                ->comment('Operator/airline ICAO code');
 
            $table->string('operator_icao', 20)->nullable();
            $table->string('operator_iata', 20)->nullable();
            $table->string('flight_number', 10)->nullable();
 
            // ---------------------------------------------------------------------------
            // Timestamps — scheduled / estimated / actual
            // All gate times (out/in) and runway times (off/on) per AeroAPI v4 naming.
            // ---------------------------------------------------------------------------
 
            // Gate departure (pushback)
            $table->timestamp('scheduled_out')->nullable()->comment('Scheduled gate departure');
            $table->timestamp('estimated_out')->nullable()->comment('Estimated gate departure');
            $table->timestamp('actual_out')->nullable()->comment('Actual gate departure');
 
            // Runway departure (wheels off)
            $table->timestamp('scheduled_off')->nullable()->comment('Scheduled runway departure');
            $table->timestamp('estimated_off')->nullable()->comment('Estimated runway departure');
            $table->timestamp('actual_off')->nullable()->comment('Actual runway departure');
 
            // Runway arrival (wheels on)
            $table->timestamp('scheduled_on')->nullable()->comment('Scheduled runway arrival');
            $table->timestamp('estimated_on')->nullable()->comment('Estimated runway arrival');
            $table->timestamp('actual_on')->nullable()->comment('Actual runway arrival');
 
            // Gate arrival (at gate)
            $table->timestamp('scheduled_in')->nullable()->comment('Scheduled gate arrival');
            $table->timestamp('estimated_in')->nullable()->comment('Estimated gate arrival');
            $table->timestamp('actual_in')->nullable()->comment('Actual gate arrival');
 
            // ---------------------------------------------------------------------------
            // Flight status flags
            // ---------------------------------------------------------------------------
            $table->boolean('position_only')->default(false)
                ->comment('True if this is a position-only (no filed plan) flight');
 
            $table->boolean('blocked')->default(false)
                ->comment('True if the flight is blocked from public display');
 
            $table->boolean('cancelled')->default(false)
                ->comment('True if the flight has been cancelled');
 
            $table->boolean('diverted')->default(false)
                ->comment('True if the flight has been diverted');
 
            // ---------------------------------------------------------------------------
            // Error / diagnostic
            // ---------------------------------------------------------------------------
            $table->string('flight_error')->nullable()
                ->comment('Error string returned by FlightAware if flight data is unavailable');
 
            // ---------------------------------------------------------------------------
            // Raw payload — always store for auditability / schema evolution
            // ---------------------------------------------------------------------------
            $table->json('raw_payload')
                ->comment('Complete raw JSON payload received from FlightAware');
 
            // ---------------------------------------------------------------------------
            // HTTP metadata
            // ---------------------------------------------------------------------------
            $table->string('source_ip', 45)->nullable()
                ->comment('IP address FlightAware POSTed from');

            $table->timestamps();
        });
    }

	// =========================================================================
    public function down(): void {
        Schema::dropIfExists('watch_callbacks');
    }
};
