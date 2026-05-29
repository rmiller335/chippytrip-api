<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// =============================================================================
return new class extends Migration {
	// =========================================================================
    public function up(): void {
        Schema::table('flights', function (Blueprint $tbl) {
			$tbl->string('equipment')->after('duration')->nullable();
			$tbl->string('meal_service')->after('equipment')->nullable();

			$tbl->unsignedInteger('first_seats')->after('meal_service')->nullable();
			$tbl->unsignedInteger('business_seats')->after('first_seats')->nullable();
			$tbl->unsignedInteger('coach_seats')->after('business_seats')->nullable();
        });
    }

	// =========================================================================
    public function down(): void {
        Schema::table('flights', function (Blueprint $tbl) {
			$tbl->dropColumn('equipment');
			$tbl->dropColumn('meal_service');

			$tbl->dropColumn('first_seats');
			$tbl->dropColumn('business_seats');
			$tbl->dropColumn('coach_seats');
        });
    }
};
