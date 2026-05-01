<?php

namespace App\Providers;

use App\Services\FlightLookupSvc;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

// =============================================================================
class FlightLookupProvider extends ServiceProvider {
	// =========================================================================
    public function register(): void {
		$this->app->singleton(FlightLookupSvc::class, function(Application $app) {
			return new FlightLookupSvc();
		});
    }

	// =========================================================================
    public function boot(): void {
        //
    }
}
