<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// =============================================================================
return new class extends Migration {
	// =========================================================================
	// One row per FlightAware callback: how far the callback's flight date is
	// from the watched departure date. Kept apart from watch_callbacks, which
	// are deleted with their watch, so alerts:window-report can look back weeks.
	public function up(): void {
		Schema::create('alert_window_log', function (Blueprint $table) {
			$table->id();
			$table->unsignedBigInteger('watch_id')->index();
			$table->unsignedBigInteger('callback_id')->nullable();
			$table->string('ident', 16)->nullable();
			$table->string('fa_flight_id')->nullable();
			$table->string('event_code', 32)->nullable();
			$table->date('watched_date');
			$table->dateTime('scheduled_out')->nullable();
			// Callback's origin-local scheduled departure date minus watched_date.
			$table->smallInteger('offset_days')->nullable();
			$table->timestamp('created_at')->useCurrent()->index();
		});
	}

	// =========================================================================
	public function down(): void {
		Schema::dropIfExists('alert_window_log');
	}
};
