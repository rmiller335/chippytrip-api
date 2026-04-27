<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// =============================================================================
return new class extends Migration {
	// =========================================================================
    public function up(): void {
		DB::table('flights')->delete();
		DB::table('listeners')->delete();

		Schema::table('flights', function(Blueprint $tbl) {
			$tbl->string('flight')->index()->after('flight_no');

			$tbl->date('departure_date')->index()->after('destination_icao');

			$tbl->dateTimeTz('departure_dt')->after('departure_date');
			$tbl->dateTimeTz('arrival_dt')->after('departure_dt');

			$tbl->unsignedInteger('duration')->after('arrival_dt');
		});

		Schema::table('listeners', function(Blueprint $tbl) {
			$tbl->dropColumn('departure_date');
			$tbl->dropColumn('departure_dt');
			$tbl->dropColumn('arrival_dt');
		});
    }

	// =========================================================================
    public function down(): void {
		DB::table('flights')->delete();
		DB::table('listeners')->delete();

		Schema::table('flights', function(Blueprint $tbl) {
			$tbl->dropColumn('flight');
			$tbl->dropColumn('departure_date');

			$tbl->dropColumn('departure_dt');
			$tbl->dropColumn('arrival_dt');

			$tbl->dropColumn('duration');
		});

		Schema::table('listeners', function(Blueprint $tbl) {
			$tbl->date('departure_date');
			$tbl->dateTime('departure_dt');
			$tbl->dateTime('arrival_dt');
		});
    }
};
