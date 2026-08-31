<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

// =============================================================================
return new class extends Migration {
	// =========================================================================
    public function up(): void {
        Schema::table('watch_callbacks', function (Blueprint $table) {
			$table->uuid('notification_id')->nullable()->after('id');
        });

		DB::table('watch_callbacks')->whereNull('notification_id')->orderBy('id')
			->each(fn ($callback) => DB::table('watch_callbacks')
				->where('id', $callback->id)
				->update(['notification_id' => (string) Str::uuid()])
			);

        Schema::table('watch_callbacks', function (Blueprint $table) {
			$table->uuid('notification_id')->nullable(false)->unique()->change();
        });
    }

	// =========================================================================
    public function down(): void {
        Schema::table('watch_callbacks', function (Blueprint $table) {
			$table->dropColumn('notification_id');
        });
    }
};
