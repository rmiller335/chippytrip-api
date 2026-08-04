<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Factory;

// =============================================================================
class AppServiceProvider extends ServiceProvider {
    // =========================================================================
    public function register(): void {
        $this->app->singleton(Messaging::class, function () {
            $credentials = config('firebase.credentials');
            $path = str_starts_with($credentials, '/') ? $credentials : base_path($credentials);

            return (new Factory())
                ->withServiceAccount($path)
                ->createMessaging();
        });
    }

    // =========================================================================
    public function boot(): void {
        //
    }
}
