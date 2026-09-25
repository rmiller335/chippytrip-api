<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// =============================================================================
return new class extends Migration {
	// =========================================================================
	// When the current FlightAware alert was created. maintenance:nightly
	// enables watches well after created_at for flights booked in advance,
	// and notifications:audit needs to know which events the alert could
	// have seen. Existing rows stay null; the audit falls back to created_at.
	public function up(): void {
		Schema::table('watches', function (Blueprint $table) {
			$table->timestamp('enabled_at')->nullable()->after('enabled');
		});
	}

	// =========================================================================
	public function down(): void {
		Schema::table('watches', function (Blueprint $table) {
			$table->dropColumn('enabled_at');
		});
	}
};
