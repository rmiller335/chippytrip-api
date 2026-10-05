<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// =============================================================================
return new class extends Migration {
	// =========================================================================
	// Synthetic accounts driven by chippy-canary. Excluded from real-user
	// metrics; see User::scopeReal().
	public function up(): void {
		Schema::table('users', function (Blueprint $table) {
			$table->boolean('is_canary')->default(false)->after('subscription_type');
		});
	}

	// =========================================================================
	public function down(): void {
		Schema::table('users', function (Blueprint $table) {
			$table->dropColumn('is_canary');
		});
	}
};
