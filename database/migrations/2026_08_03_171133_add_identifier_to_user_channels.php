<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('user_channels', function (Blueprint $table) {
			$table->dropUnique(['user_id', 'channel']);
			$table->string('identifier')->after('channel');
			$table->unique(['user_id', 'channel', 'identifier']);
        });
    }

    public function down(): void {
        Schema::table('user_channels', function (Blueprint $table) {
			$table->dropUnique(['user_id', 'channel', 'identifier']);
			$table->dropColumn('identifier');
			$table->unique(['user_id', 'channel']);
        });
    }
};
