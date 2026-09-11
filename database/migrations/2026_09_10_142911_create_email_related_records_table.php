<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('email_related_records', function (Blueprint $table) {
            $table->id();

			$table->foreignId('inbound_email_id')
				->constrained('inbound_emails')
				->cascadeOnDelete()
			;

			$table->string('record_type');
			$table->unsignedBigInteger('record_id');

            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('email_related_records');
    }
};
