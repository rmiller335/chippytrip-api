<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Get an auth token
Route::post('/sanctum/token', [ App\Http\Controllers\Authorizer::class, 'genToken' ]);

// Return user information
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
