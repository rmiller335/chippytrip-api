<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// =============================================================================
return new class extends Migration {
	// =========================================================================
    public function up(): void {
		Schema::create('listeners', function(Blueprint $tbl) {
			$tbl->id();

			$tbl->unsignedBigInteger('user_id')->index();
			$tbl->unsignedBigInteger('watch_id')->index();

			$tbl->string('travelers');

			$tbl->timestamps();

			$tbl->foreign('user_id')->references('id')->on('users');
			$tbl->foreign('watch_id')->references('id')->on('watches');
		});
    }

	// =========================================================================
    public function down(): void {
		Schema::drop('listeners');
    }
};
