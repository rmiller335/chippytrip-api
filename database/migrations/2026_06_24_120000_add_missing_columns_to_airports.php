<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// =============================================================================
return new class extends Migration {
	// =========================================================================
    public function up(): void {
		Schema::table('airports', function (Blueprint $tbl) {
			$tbl->integer('elevation')->nullable()->after('timezone');
			$tbl->string('wiki_url')->nullable()->after('elevation');
			$tbl->string('flights_url')->nullable()->after('wiki_url');
			$tbl->json('alternatives')->nullable()->after('flights_url');
		});
    }

	// =========================================================================
    public function down(): void {
		Schema::table('airports', function (Blueprint $tbl) {
			$tbl->dropColumn('elevation');
			$tbl->dropColumn('wiki_url');
			$tbl->dropColumn('flights_url');
			$tbl->dropColumn('alternatives');
		});
    }
};
