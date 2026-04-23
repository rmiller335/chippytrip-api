<?php

namespace App\Providers;

use App\Services\AviationEdgeSvc;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

// =============================================================================
class AviationEdgeProvider extends ServiceProvider {
	// =========================================================================
    public function register(): void {
		$this->app->singleton(AviationEdgeSvc::class, function(Application $app) {
			return new AviationEdgeSvc();
		});
    }

	// =========================================================================
    public function boot(): void {
        //
    }
}
