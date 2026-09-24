<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// =============================================================================
return new class extends Migration {
	// =========================================================================
	// Callbacks were linked to their watch only through alert_id =
	// watches.subscription_id, which Watch::disable() nulls — so a watch lost
	// its callbacks once its alert window ended. Link them by id instead.
    public function up(): void {
        Schema::table('watch_callbacks', function (Blueprint $table) {
			$table->foreignId('watch_id')->nullable()->after('alert_id')
				->constrained('watches')->cascadeOnDelete();
        });

		// Only callbacks of still-enabled watches can be matched. The rest
		// stay null and are removed by maintenance:nightly.
		DB::table('watch_callbacks')->update([
			'watch_id' => DB::raw('(select id from watches where watches.subscription_id = watch_callbacks.alert_id)'),
		]);
    }

	// =========================================================================
    public function down(): void {
        Schema::table('watch_callbacks', function (Blueprint $table) {
			$table->dropConstrainedForeignId('watch_id');
        });
    }
};
