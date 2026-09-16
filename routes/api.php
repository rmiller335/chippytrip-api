<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Get an auth token
Route::post('/sanctum/token', [
	App\Http\Controllers\Authorizer::class, 'genToken'
]);

// Return user information
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Flightaware watch callback
Route::post('/watch-callback', [
	App\Http\Controllers\WatchCallback::class, 'callback'
]);

Route::post('/postmark/inbound', [
	App\Http\Controllers\PostmarkInboundController::class, 'handle']
);

// Sync with mobile app.
Route::middleware('auth:sanctum')->get('/sync/listeners', [
	App\Http\Controllers\ListenerSyncController::class, 'index'
]);

// Set the FCM token for push notifications.
Route::middleware('auth:sanctum')->post('/fcm-token', [
	App\Http\Controllers\FcmTokenController::class, 'store'
]);

// Validate a flight number + date, or search by route — used by the
// mobile app's "Add flight" modal.
Route::middleware('auth:sanctum')->post('/flights/validate', [
	App\Http\Controllers\FlightSearchController::class, 'checkFlight'
]);

Route::middleware('auth:sanctum')->post('/flights/search', [
	App\Http\Controllers\FlightSearchController::class, 'search'
]);

// Add a flight to watch for notifications.
Route::middleware('auth:sanctum')->post('/watches', [
	App\Http\Controllers\FlightSearchController::class, 'watch'
]);

// Type-ahead suggestions for the "Add flight" modal.
Route::middleware('auth:sanctum')->get('/airports/search', [
	App\Http\Controllers\AirportController::class, 'search'
]);

Route::middleware('auth:sanctum')->get('/airlines/search', [
	App\Http\Controllers\AirlineController::class, 'search'
]);
