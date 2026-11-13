<?php

use App\Http\Controllers\Web\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Web\Company\DashboardController as CompanyDashboardController;
use App\Http\Controllers\Web\Promoter\DashboardController as PromoterDashboardController;
use App\Http\Controllers\Web\SpaceRedirectController;
use App\Http\Controllers\Web\TwoFactorEnrollmentController;
use App\Models\SmsMessage;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/espace', SpaceRedirectController::class)->name('space');
    Route::get('/securite/double-authentification', TwoFactorEnrollmentController::class)->name('security.two-factor');

    Route::middleware('two-factor.enrolled')->group(function () {
        Route::prefix('admin')->name('admin.')->middleware('space:admin')->group(function () {
            Route::get('/', AdminDashboardController::class)->name('dashboard');
        });

        Route::prefix('compagnie')->name('company.')->middleware('space:company')->group(function () {
            Route::get('/', CompanyDashboardController::class)->name('dashboard');
        });

        Route::prefix('promoteur')->name('promoter.')->middleware('space:promoter')->group(function () {
            Route::get('/', PromoterDashboardController::class)->name('dashboard');
        });
    });
});

if (app()->isLocal()) {
    // Boîte d'envoi des SMS simulés (codes OTP), uniquement en local.
    Route::get('/dev/sms', fn () => view('dev.sms', [
        'messages' => SmsMessage::query()->latest('id')->limit(50)->get(),
    ]))->name('dev.sms');
}
