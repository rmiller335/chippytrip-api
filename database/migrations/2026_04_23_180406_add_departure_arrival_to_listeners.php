<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// =============================================================================
return new class extends Migration {
	// =========================================================================
    public function up(): void {
        Schema::table('listeners', function (Blueprint $tbl) {
			$tbl->after('watch_id', function(Blueprint $tbl) {
				$tbl->date('departure_date');
				$tbl->dateTime('departure_dt');
				$tbl->dateTime('arrival_dt');
			});
        });
    }

	// =========================================================================
    public function down(): void {
        Schema::table('listeners', function (Blueprint $tbl) {
			$tbl->dropColumn('departure_date');
			$tbl->dropColumn('departure_dt');
			$tbl->dropColumn('arrival_dt');
        });
    }
};
