<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// =============================================================================
return new class extends Migration {
	// =========================================================================
	// The app sends a stable device ID as the token name, and each sign-in
	// created another token that never expires. Keep one token per user and
	// device: the one used most recently (or, if never used, created most
	// recently), which is the one the device holds. The rest are deleted.
	// A token name longer than 255 characters would fail the column change.
    public function up(): void {
		// Tokens issued before AppServiceProvider's morph map have the class
		// name as their type, so $user->tokens() (which uses 'user') misses
		// them. Bring them in line so they count as the same device.
		DB::table('personal_access_tokens')
			->where('tokenable_type', 'App\\Models\\User')
			->update(['tokenable_type' => 'user']);

		$duplicates = DB::table('personal_access_tokens')
			->select('tokenable_type', 'tokenable_id', 'name')
			->groupBy('tokenable_type', 'tokenable_id', 'name')
			->havingRaw('COUNT(*) > 1')
			->get();

		foreach ($duplicates as $device) {
			$tokens = DB::table('personal_access_tokens')
				->where('tokenable_type', $device->tokenable_type)
				->where('tokenable_id', $device->tokenable_id)
				->where('name', $device->name);

			$keep = (clone $tokens)
				->orderByRaw('COALESCE(last_used_at, created_at) DESC')
				->orderByDesc('id')
				->value('id');

			$tokens->where('id', '!=', $keep)->delete();
		}

        Schema::table('personal_access_tokens', function (Blueprint $table) {
			$table->string('name')->change();
			$table->unique(['tokenable_type', 'tokenable_id', 'name'], 'personal_access_tokens_device_unique');
        });
    }

	// =========================================================================
    public function down(): void {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
			$table->dropUnique('personal_access_tokens_device_unique');
			$table->text('name')->change();
        });
    }
};
