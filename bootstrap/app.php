<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        // Outside the api group: X-Health-Token instead of Sanctum.
        then: function () {
            Illuminate\Support\Facades\Route::middleware(App\Http\Middleware\RequireHealthToken::class)
                ->group(function () {
                    Illuminate\Support\Facades\Route::get('/health', App\Http\Controllers\HealthController::class);
                    Illuminate\Support\Facades\Route::get('/health/notifications', App\Http\Controllers\NotificationAuditController::class);
                });
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // There's no login page; unauthenticated requests get a JSON 401.
        $middleware->redirectGuestsTo(null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API only: always render errors as JSON, whatever the Accept header.
        $exceptions->shouldRenderJsonWhen(fn () => true);
    })->create();
