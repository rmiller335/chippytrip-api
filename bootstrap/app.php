<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // Outside the web/api groups: no session, no auth.
        then: function () {
            Illuminate\Support\Facades\Route::get('/health', App\Http\Controllers\HealthController::class);
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
		//
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
