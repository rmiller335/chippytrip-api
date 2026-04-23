<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// =============================================================================
return new class extends Migration {
	// =========================================================================
    public function up(): void {
		Schema::create('flights', function(Blueprint $tbl) {
			$tbl->id();

			$tbl->string('airline_icao')->index();
			$tbl->string('flight_no')->index();
			$tbl->string('origin_icao')->index();
			$tbl->string('destination_icao')->index();

			$tbl->timestamps();
		});
    }

	// =========================================================================
    public function down(): void {
		Schema::drop('flights');
    }
};
