<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// =============================================================================
return new class extends Migration {
	// =========================================================================
    public function up(): void {
        Schema::create('watches_notifications', function (Blueprint $table) {
			$table->foreignId('watch_id')->constrained()->cascadeOnDelete();
			$table->uuid('notification_id')->constrained('notifications')->cascadeOnDelete();

			$table->primary(['watch_id', 'notification_id']);
        });
    }

	// =========================================================================
    public function down(): void {
        Schema::dropIfExists('watches_notifications');
    }
};
