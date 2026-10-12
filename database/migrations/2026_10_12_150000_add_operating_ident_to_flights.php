<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// =============================================================================
return new class extends Migration {
	// =========================================================================
	// The ICAO ident FlightAware tracks the flight under. Differs from the
	// marketing flight for codeshares (AA6126 is flown as PDT6126), and an
	// AeroAPI alert on the marketing ident never fires.
	public function up(): void {
		Schema::table('flights', function (Blueprint $table) {
			$table->string('operating_ident', 16)->nullable()->after('flight_no');
		});
	}

	// =========================================================================
	public function down(): void {
		Schema::table('flights', function (Blueprint $table) {
			$table->dropColumn('operating_ident');
		});
	}
};
