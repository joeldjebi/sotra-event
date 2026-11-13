<?php

use App\Enums\TokenAbility;
use App\Http\Controllers\Api\V1\Auth\OtpController;
use App\Http\Controllers\Api\V1\Customer\EventController;
use App\Http\Controllers\Api\V1\Customer\OrderController;
use App\Http\Controllers\Api\V1\Customer\PassController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\Staff\MissionController;
use App\Http\Controllers\Api\V1\Staff\ScanController;
use App\Http\Controllers\Api\V1\Webhooks\PaymentWebhookController;
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

    // Catalogue public (SP-01 à SP-05)
    Route::get('events', [EventController::class, 'index'])->name('events.index');
    Route::get('events/{event}', [EventController::class, 'show'])->whereNumber('event')->name('events.show');

    // Spectateur connecté
    Route::middleware(['auth:sanctum', 'abilities:'.TokenAbility::Customer->value])->group(function () {
        Route::post('orders/quote', [OrderController::class, 'quote'])->name('orders.quote');
        Route::post('orders', [OrderController::class, 'store'])->middleware('throttle:30,1')->name('orders.store');
        Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::post('orders/{order}/pay', [OrderController::class, 'pay'])->middleware('throttle:10,1')->name('orders.pay');

        Route::get('me/passes', [PassController::class, 'index'])->name('passes.index');
        Route::get('passes/{pass}', [PassController::class, 'show'])->name('passes.show');
        Route::post('passes/{pass}/transfer', [PassController::class, 'transfer'])->middleware('throttle:10,1')->name('passes.transfer');
        Route::get('passes/{pass}/trip', [PassController::class, 'trip'])->name('passes.trip');
    });

    // App convoyeur (CV-01 à CV-19) : missions, manifeste hors ligne, scans, positions.
    Route::middleware(['auth:sanctum', 'abilities:'.TokenAbility::Conveyor->value, 'staff.device'])->group(function () {
        Route::get('me/missions', [MissionController::class, 'index'])->name('missions.index');
        Route::get('missions/{mission}/manifest', [MissionController::class, 'manifest'])->name('missions.manifest');
        Route::get('missions/{mission}/boarding', [MissionController::class, 'boarding'])->name('missions.boarding');
        Route::post('missions/{mission}/boarding-started', [MissionController::class, 'boardingStarted'])->name('missions.boarding-started');
        Route::post('missions/{mission}/depart', [MissionController::class, 'depart'])->name('missions.depart');
        Route::post('missions/{mission}/arrive', [MissionController::class, 'arrive'])->name('missions.arrive');
        Route::post('missions/{mission}/positions', [MissionController::class, 'positions'])->middleware('throttle:120,1')->name('missions.positions');
        Route::post('scans', [ScanController::class, 'store'])->middleware('throttle:240,1')->name('scans.store');
        Route::post('scans/sync', [ScanController::class, 'sync'])->middleware('throttle:60,1')->name('scans.sync');
    });

    // Agrégateur de paiement (PA-02)
    Route::post('payments/webhook/{provider}', PaymentWebhookController::class)->middleware('throttle:120,1')->name('payments.webhook');
});
