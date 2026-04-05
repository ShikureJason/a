<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GatewaySession;
use App\Http\Controllers\GatewayController;


Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('session')
        ->controller(GatewaySession::class)
        ->group(function () {
            Route::post('/create/{locationId}', 'createSession')->name('session.create');
            Route::post('/close/{sessionId}', 'closeSession')->name('session.close');
        });
});
Route::prefix('esp')->group(function () {
    Route::post('/upload', [GatewayController::class, 'upload'])
    ->middleware('device.auth');
});

