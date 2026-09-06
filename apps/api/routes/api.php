<?php

use App\Http\Controllers\Api\HealthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/health/ready', [HealthController::class, 'ready']);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
