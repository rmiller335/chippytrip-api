<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// =============================================================================
return new class extends Migration
{
	// =========================================================================
    public function up(): void {
        Schema::table('watches', function (Blueprint $table) {
			$table->string('subscription_id')->after('flight_id')->nullable()->change();
			$table->string('secret')->after('enabled')->nullable()->change();
        });
    }

	// =========================================================================
    public function down(): void {
        Schema::table('watches', function (Blueprint $table) {
			$table->string('secret')->nullable(false)->change();
			$table->string('subscription_id')->nullable(false)->change();
        });
    }
};
