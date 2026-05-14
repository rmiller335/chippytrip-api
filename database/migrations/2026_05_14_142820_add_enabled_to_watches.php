<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// =============================================================================
return new class extends Migration {
	// =========================================================================
    public function up(): void {
        Schema::table('watches', function (Blueprint $table) {
			$table->boolean('enabled')->after('subscription_id')->default(true)->index();
        });
    }

	// =========================================================================
    public function down(): void {
        Schema::table('watches', function (Blueprint $table) {
			$table->dropColumn('enabled');
        });
    }
};
