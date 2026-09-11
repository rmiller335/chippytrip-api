<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// =============================================================================
return new class extends Migration {
	// =========================================================================
	public function up(): void
	{
		Schema::create('errors', function (Blueprint $table) {
			$table->id();

			$table->unsignedBigInteger('errorable_id');
			$table->string('errorable_type');
			$table->string('message');
			$table->string('code')->nullable(); // e.g. exception class, error code
			$table->json('context')->nullable(); // any extra structured data

			$table->timestamps();

			$table->index(['errorable_type', 'errorable_id']);
		});
	}

	// =========================================================================
	public function down(): void {
		Schema::dropIfExists('errors');
	}
};
