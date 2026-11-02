<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/share/incidents/{id}', [\App\Http\Controllers\ShareController::class, 'incident'])->where('id', '[0-9A-Za-z]{26}');
