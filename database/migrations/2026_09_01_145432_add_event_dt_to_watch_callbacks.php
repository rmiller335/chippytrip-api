<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('watch_callbacks', function (Blueprint $table) {
			$table->dateTime('event_dt')->nullable()->after('event_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('watch_callbacks', function (Blueprint $table) {
			$table->dropColumn('event_dt');
        });
    }
};
