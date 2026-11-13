<?php

use App\Enums\TokenAbility;
use App\Http\Controllers\Api\V1\Auth\OtpController;
use App\Http\Controllers\Api\V1\Customer\EventController;
use App\Http\Controllers\Api\V1\Customer\FeedbackController;
use App\Http\Controllers\Api\V1\Customer\OrderController;
use App\Http\Controllers\Api\V1\Customer\PassController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\Staff\BreakdownController;
use App\Http\Controllers\Api\V1\Staff\MissionController;
use App\Http\Controllers\Api\V1\Staff\ScanController;
use App\Http\Controllers\Api\V1\Tech\TechController;
use App\Http\Controllers\Api\V1\Webhooks\InboundSmsController;
use App\Http\Controllers\Api\V1\Webhooks\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

/*
| API EVENT TRANS v1 : applications spectateur, convoyeur et service technique.
| Préfixe /api/v1. Documentation OpenAPI générée par Scramble sur /docs/api.
*/
Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('health', HealthController::class)->middleware('throttle:60,1')->name('health');

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
        Route::post('passes/{pass}/cancel', [PassController::class, 'cancel'])->middleware('throttle:10,1')->name('passes.cancel');
        Route::post('passes/{pass}/review', [FeedbackController::class, 'review'])->middleware('throttle:10,1')->name('passes.review');
        Route::post('passes/{pass}/complaints', [FeedbackController::class, 'complain'])->middleware('throttle:10,1')->name('passes.complaints');
        Route::get('me/complaints', [FeedbackController::class, 'complaints'])->name('complaints.index');
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
        Route::post('breakdowns', [BreakdownController::class, 'store'])->middleware('throttle:20,1')->name('breakdowns.store');
    });

    // App service technique (ST-01 à ST-16)
    Route::prefix('tech')->name('tech.')->middleware(['auth:sanctum', 'abilities:'.TokenAbility::Tech->value, 'staff.device'])->group(function () {
        Route::get('team', [TechController::class, 'team'])->name('team');
        Route::patch('team', [TechController::class, 'updateTeam'])->name('team.update');
        Route::post('position', [TechController::class, 'position'])->middleware('throttle:120,1')->name('position');
        Route::get('alerts', [TechController::class, 'alerts'])->name('alerts');
        Route::post('alerts/{alert}/take', [TechController::class, 'take'])->whereNumber('alert')->name('alerts.take');
        Route::get('reserve-buses', [TechController::class, 'reserveBuses'])->name('reserve-buses');
        Route::get('interventions', [TechController::class, 'history'])->name('interventions.index');
        Route::get('interventions/{intervention}', [TechController::class, 'show'])->name('interventions.show');
        Route::post('interventions/{intervention}/steps', [TechController::class, 'step'])->name('interventions.step');
        Route::post('interventions/{intervention}/close', [TechController::class, 'close'])->name('interventions.close');
    });

    // Passerelle SMS : alerte panne en mode dégradé (CV-19)
    Route::post('sms/inbound', InboundSmsController::class)->middleware('throttle:60,1')->name('sms.inbound');

    // Agrégateur de paiement (PA-02)
    Route::post('payments/webhook/{provider}', PaymentWebhookController::class)->middleware('throttle:120,1')->name('payments.webhook');
});
