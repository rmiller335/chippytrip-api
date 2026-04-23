<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// =============================================================================
return new class extends Migration {
	// =========================================================================
    public function up(): void {
		Schema::create('airlines', function(Blueprint $tbl) {
			$tbl->id();

			$tbl->string('icao')->unique();
			$tbl->string('iata')->index();
			$tbl->string('call_sign')->index();
			$tbl->string('name');
			$tbl->string('country_code')->index();
			$tbl->string('status')->index();
			$tbl->string('types');

			$tbl->timestamps();
		});

		Schema::table('flights', function(Blueprint $tbl) {
			$tbl->foreign('airline_icao')->references('icao')->on('airlines');
		});
    }

	// =========================================================================
    public function down(): void {
		Schema::disableForeignKeyConstraints();
		Schema::drop('airlines');
		Schema::enableForeignKeyConstraints();
    }
};
