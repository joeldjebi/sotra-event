<?php

use App\Enums\Permission;
use App\Http\Controllers\Web\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Web\Admin\EventEditorController;
use App\Http\Controllers\Web\Company\DashboardController as CompanyDashboardController;
use App\Http\Controllers\Web\Promoter\DashboardController as PromoterDashboardController;
use App\Http\Controllers\Web\SpaceRedirectController;
use App\Http\Controllers\Web\TwoFactorEnrollmentController;
use App\Models\SmsMessage;
use App\Support\LivewirePage;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/espace', SpaceRedirectController::class)->name('space');
    Route::get('/securite/double-authentification', TwoFactorEnrollmentController::class)->name('security.two-factor');

    // La 2FA est obligatoire (NF-03) : la désactivation proposée par Fortify est neutralisée.
    // Seul un administrateur peut la réinitialiser (Utilisateurs & rôles).
    if (config('eventtrans.two_factor.enforced')) {
        Route::delete('/user/two-factor-authentication', fn () => abort(403, 'La double authentification est obligatoire.'))
            ->name('two-factor.disable');
    }

    Route::middleware('two-factor.enrolled')->group(function () {
        Route::prefix('admin')->name('admin.')->middleware('space:admin')->group(function () {
            Route::get('/', AdminDashboardController::class)->name('dashboard');

            LivewirePage::route('evenements', 'events', 'admin.events-table', 'Événements', Permission::EventsManage);
            Route::middleware('can:'.Permission::EventsManage->value)->group(function () {
                Route::get('evenements/nouveau', [EventEditorController::class, 'create'])->name('events.create');
                Route::get('evenements/{event}', [EventEditorController::class, 'edit'])->name('events.edit');
            });
            LivewirePage::route('compagnies', 'companies', 'admin.companies-table', 'Compagnies', Permission::CompaniesManage);
            LivewirePage::route('promoteurs', 'promoters', 'admin.promoters-table', 'Promoteurs', Permission::PromotersManage);
            LivewirePage::route('lieux', 'venues', 'admin.venues-table', 'Lieux', Permission::EventsManage);
            LivewirePage::route('utilisateurs', 'users', 'admin.users-table', 'Utilisateurs & rôles', Permission::UsersManage);
            LivewirePage::route('journal', 'audit', 'admin.audit-log-table', 'Journal d\'audit', Permission::AuditView);
        });

        Route::prefix('compagnie')->name('company.')->middleware('space:company')->group(function () {
            Route::get('/', CompanyDashboardController::class)->name('dashboard');

            LivewirePage::route('evenements-proposes', 'proposed-events', 'company.proposed-events', 'Événements proposés', Permission::ProposedEventsRespond);
            LivewirePage::route('plan-de-transport', 'transport-plan', 'company.transport-plan', 'Plan de transport', Permission::OperationsManage);
            LivewirePage::route('bus', 'buses', 'company.buses-table', 'Bus', Permission::FleetManage);
            LivewirePage::route('depots', 'depots', 'company.depots-table', 'Dépôts', Permission::FleetManage);
            LivewirePage::route('personnel', 'staff', 'company.staff-table', 'Chauffeurs & convoyeurs', Permission::StaffManage);
            LivewirePage::route('equipes-techniques', 'tech-teams', 'company.tech-teams-table', 'Équipes service technique', Permission::TechTeamsManage);
            LivewirePage::route('terminaux', 'devices', 'company.devices-table', 'PDA & terminaux', Permission::DevicesManage);
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
