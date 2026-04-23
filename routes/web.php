<?php

use Illuminate\Support\Facades\Route;

// Auth error goes here. There is no login for an API ...
Route::get('/login', function (Request $request) {
	abort(403, 'Unauthorized');
})->name('login');


// Docs eventually?
Route::get('/', function () {
    return view('welcome');
});
