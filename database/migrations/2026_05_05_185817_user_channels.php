<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// =============================================================================
return new class extends Migration {
	// =========================================================================
    public function up(): void {
		Schema::create('user_channels', function(Blueprint $tbl) {
			$tbl->id();

			$tbl->unsignedBigInteger('user_id')->foreign('id')->on('users');
			$tbl->string('channel')->index();
			$tbl->json('credentials');

			$tbl->timestamps();

			$tbl->unique([ 'user_id', 'channel' ]);
		});
    }

	// =========================================================================
    public function down(): void {
		Schema::drop('user_channels');
    }
};
