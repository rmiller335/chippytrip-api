<?php

use App\Services\FlightAwareSvc;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

// =============================================================================
return new class extends Migration {
	// =========================================================================
    public function up(): void {
		$this->deleteRecs();

        Schema::table('flights', function (Blueprint $table) {
			$table->date('alert_start')->after('duration')->index();
			$table->date('alert_end')->after('alert_start')->index();
        });
    }

	// =========================================================================
    public function down(): void {
        Schema::table('flights', function (Blueprint $table) {
			$table->dropColumn('alert_start');
			$table->dropColumn('alert_end');
        });
    }

	// =========================================================================
	protected function deleteRecs() {
		$fa = new FlightAwareSvc();

		App\Models\Listener::query()->delete();
		App\Models\Watch::query()->delete();
		App\Models\Flight::query()->delete();

		$alerts = $fa->watchList();

		foreach($alerts as $alert) {
			$fa->watchDelete($alert->id);
		}
	}
};
