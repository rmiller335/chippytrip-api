<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// =============================================================================
return new class extends Migration {
	// =========================================================================
    public function up(): void {
        Schema::create('family_members', function (Blueprint $table) {
			$table->id();

			$table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
			$table->foreignId('family_member_id')->constrained('users')->cascadeOnDelete();
			$table->boolean('auto_add')->default(false);

			$table->timestamps();

			$table->unique(['user_id', 'family_member_id']);
        });
    }

	// =========================================================================
    public function down(): void {
        Schema::dropIfExists('family_members');
    }
};
