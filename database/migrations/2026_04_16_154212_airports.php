<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// =============================================================================
return new class extends Migration {
	// =========================================================================
    public function up(): void {
		Schema::create('airports', function(Blueprint $tbl) {
			$tbl->id();

			$tbl->string('icao')->index()->unique();
			$tbl->string('iata')->index();
			$tbl->string('name');
			$tbl->string('city');
			$tbl->string('state');
			$tbl->string('country_code');
			$tbl->double('latitude');
			$tbl->double('longitude');
			$tbl->string('timezone');

			$tbl->timestamps();
		});

		Schema::table('flights', function(Blueprint $tbl) {
			$tbl->foreign('origin_icao')->references('icao')->on('airports');
			$tbl->foreign('destination_icao')->references('icao')->on('airports');
		});
    }

	// =========================================================================
    public function down(): void {
		Schema::disableForeignKeyConstraints();
		Schema::drop('airports');
		Schema::enableForeignKeyConstraints();
    }
};
