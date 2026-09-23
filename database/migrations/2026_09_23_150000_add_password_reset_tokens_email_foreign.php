<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// =============================================================================
return new class extends Migration {
	// =========================================================================
    public function up(): void {
		// Tokens for emails that no longer belong to a user are dead anyway.
		DB::table('password_reset_tokens')
			->whereNotIn('email', DB::table('users')->select('email'))
			->delete();

        Schema::table('password_reset_tokens', function (Blueprint $table) {
			$table->foreign('email')->references('email')->on('users')
				->cascadeOnUpdate()->cascadeOnDelete();
        });
    }

	// =========================================================================
    public function down(): void {
        Schema::table('password_reset_tokens', function (Blueprint $table) {
			$table->dropForeign(['email']);
        });
    }
};
