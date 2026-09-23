<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// =============================================================================
// A null password marks a user (e.g. a family member added by another user)
// as not having completed first-time setup yet.
return new class extends Migration {
	// =========================================================================
    public function up(): void {
        Schema::table('users', function (Blueprint $table) {
			$table->string('password')->nullable()->change();
        });
    }

	// =========================================================================
    public function down(): void {
        Schema::table('users', function (Blueprint $table) {
			$table->string('password')->nullable(false)->change();
        });
    }
};
