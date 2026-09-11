<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// =============================================================================
return new class extends Migration {
	// =========================================================================
	public function up(): void
	{
		Schema::create('inbound_emails', function (Blueprint $table) {
			$table->id();

			$table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

			$table->string('message_id')->nullable()->index();
			$table->string('from_address');
			$table->string('subject')->nullable();

			$table->longText('text_body')->nullable();
			$table->longText('html_body')->nullable();

			$table->string('status')->default('pending');
			$table->text('error')->nullable();

			$table->timestamps();
		});
	}

	// =========================================================================
	public function down(): void {
		Schema::dropIfExists('inbound_emails');
	}
};
