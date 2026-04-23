<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// =============================================================================
return new class extends Migration {
	// =========================================================================
    public function up(): void {
		Schema::create('watches', function(Blueprint $tbl) {
			$tbl->id();

			$tbl->unsignedBigInteger('flight_id')->index();
			$tbl->string('subscription_id')->index();

			$tbl->timestamps();

			$tbl->foreign('flight_id')->references('id')->on('flights');
		});
    }

	// =========================================================================
    public function down(): void {
		Schema::drop('watches');
    }
};
