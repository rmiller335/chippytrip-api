<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// =============================================================================
return new class extends Migration {
	// =========================================================================
    public function up(): void {
		Schema::table('flights', function(Blueprint $tbl) {
			$tbl->dateTime('departure_dt')->nullable()->change();
			$tbl->dateTime('arrival_dt')->nullable()->change();
			$tbl->unsignedInteger('duration')->nullable()->change();
		});
    }

	// =========================================================================
    public function down(): void {
		// I'm going to go ahead and leave them nullable.
    }
};
