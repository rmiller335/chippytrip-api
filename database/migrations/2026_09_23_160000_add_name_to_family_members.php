<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// =============================================================================
return new class extends Migration {
	// =========================================================================
	// The name a user gives their family member. It's per-user so one user
	// can't rename another user's account.
    public function up(): void {
        Schema::table('family_members', function (Blueprint $table) {
			$table->string('name')->nullable()->after('family_member_id');
        });

		// Correlated subquery rather than update…join: Laravel's SQLite
		// grammar can't reference the joined table in the SET clause.
		DB::table('family_members')->update([
			'name' => DB::raw('(select name from users where users.id = family_members.family_member_id)'),
		]);

        Schema::table('family_members', function (Blueprint $table) {
			$table->string('name')->nullable(false)->change();
        });
    }

	// =========================================================================
    public function down(): void {
        Schema::table('family_members', function (Blueprint $table) {
			$table->dropColumn('name');
        });
    }
};
