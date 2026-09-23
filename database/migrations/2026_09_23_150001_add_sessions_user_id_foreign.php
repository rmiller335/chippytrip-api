<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// =============================================================================
return new class extends Migration {
	// =========================================================================
    public function up(): void {
		// Sessions belonging to users that no longer exist are dead anyway.
		DB::table('sessions')
			->whereNotNull('user_id')
			->whereNotIn('user_id', DB::table('users')->select('id'))
			->delete();

        Schema::table('sessions', function (Blueprint $table) {
			$table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

	// =========================================================================
    public function down(): void {
        Schema::table('sessions', function (Blueprint $table) {
			$table->dropForeign(['user_id']);
        });
    }
};
