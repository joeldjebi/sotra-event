<?php

use App\Http\Controllers\Api\V1\Auth\OtpController;
use App\Http\Controllers\Api\V1\MeController;
use Illuminate\Support\Facades\Route;

/*
| API EVENT TRANS v1 : applications spectateur, convoyeur et service technique.
| Préfixe /api/v1. Documentation OpenAPI générée par Scramble sur /docs/api.
*/
Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('otp', [OtpController::class, 'send'])->middleware('throttle:otp-send')->name('otp');
        Route::post('verify', [OtpController::class, 'verify'])->middleware('throttle:otp-verify')->name('verify');
        Route::post('logout', [OtpController::class, 'logout'])->middleware('auth:sanctum')->name('logout');
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', MeController::class)->name('me');
    });
});
