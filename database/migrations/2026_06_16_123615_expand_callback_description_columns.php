<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// =============================================================================
return new class extends Migration {
	// =========================================================================
    public function up(): void {
		Schema::table('watch_callbacks', function(Blueprint $tbl) {
			$tbl->longText('summary')->nullable()->change();
			$tbl->longText('short_description')->nullable()->change();
		});
    }

	// =========================================================================
    public function down(): void {
		Schema::table('watch_callbacks', function(Blueprint $tbl) {
			$tbl->string('summary')->nullable()->change();
			$tbl->string('short_description')->nullable()->change();
		});
    }
};
