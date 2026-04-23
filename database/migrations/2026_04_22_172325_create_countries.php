<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// =============================================================================
return new class extends Migration {
	// x============================================================================
    public function up(): void {
        Schema::create('countries', function (Blueprint $tbl) {
            $tbl->id();

			$tbl->string('iso2')->unique();
			$tbl->string('iso3')->unique();
			$tbl->string('name');
			$tbl->string('dominion')->nullable();

            $tbl->timestamps();
        });

		Artisan::call('countries:update');

		Schema::table('airlines', function(Blueprint $tbl) {
			$tbl->foreign('country_code')->references('iso2')->on('countries');
		});

		DB::table('airports')->whereNotIn('country_code', function($qry) {
			$qry->select('iso2')
				->from('countries')
			;
		})->delete();

		Schema::table('airports', function(Blueprint $tbl) {
			$tbl->foreign('country_code')->references('iso2')->on('countries');
		});
    }

	// x============================================================================
    public function down(): void {
		Schema::table('airlines', function(Blueprint $tbl) {
			$tbl->dropForeign('airlines_country_code_foreign');
		});

		Schema::table('airports', function(Blueprint $tbl) {
			$tbl->dropForeign('airports_country_code_foreign');
		});

        Schema::dropIfExists('countries');
    }
};
