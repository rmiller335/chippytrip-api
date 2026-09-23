<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// =============================================================================
return new class extends Migration {
	// =========================================================================
    public function up(): void {
        Schema::table('email_related_records', function (Blueprint $table) {
			$table->index(['record_type', 'record_id']);
        });
    }

	// =========================================================================
    public function down(): void {
        Schema::table('email_related_records', function (Blueprint $table) {
			$table->dropIndex(['record_type', 'record_id']);
        });
    }
};
