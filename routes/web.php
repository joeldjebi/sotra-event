<?php

use App\Enums\Permission;
use App\Http\Controllers\Web\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Web\Admin\EventEditorController;
use App\Http\Controllers\Web\Company\DashboardController as CompanyDashboardController;
use App\Http\Controllers\Web\Company\QualityReportController;
use App\Http\Controllers\Web\PayoutDocumentController;
use App\Http\Controllers\Web\Promoter\PromoKitController;
use App\Http\Controllers\Web\Promoter\ReportController as PromoterReportController;
use App\Http\Controllers\Web\Site\PaymentSimulatorController;
use App\Http\Controllers\Web\Site\PublicPassController;
use App\Http\Controllers\Web\SpaceRedirectController;
use App\Http\Controllers\Web\ThemePreferenceController;
use App\Http\Controllers\Web\TwoFactorEnrollmentController;
use App\Livewire\Site\Account as SiteAccount;
use App\Livewire\Site\ChoosePass;
use App\Livewire\Site\Home as SiteHome;
use App\Livewire\Site\Login as SiteLogin;
use App\Livewire\Site\PassesIndex;
use App\Livewire\Site\PassShow;
use App\Livewire\Site\PayOrder;
use App\Livewire\Site\Trip as SiteTrip;
use App\Models\OutboundMessage;
use App\Models\SmsMessage;
use App\Support\LivewirePage;
use Illuminate\Support\Facades\Route;

/*
| Site spectateur (SP-01 à SP-25) : catalogue public, achat, Mes Pass. Session « customer » (connexion OTP).
*/
Route::get('/', SiteHome::class)->name('home');
Route::get('/evenements/{slug}', ChoosePass::class)->name('site.events.show');
Route::get('/connexion', SiteLogin::class)->middleware('guest:customer')->name('site.login');
Route::get('/pass/{pass}', PublicPassController::class)->middleware('signed')->name('site.passes.public');
Route::get('/mon-trajet', SiteTrip::class)->name('site.trip');

Route::middleware('auth:customer')->group(function () {
    Route::get('/commande/{order}/paiement', PayOrder::class)->name('site.orders.pay');
    Route::get('/mes-pass', PassesIndex::class)->name('site.passes.index');
    Route::get('/mes-pass/{pass}', PassShow::class)->name('site.passes.show');
    Route::get('/mon-compte', SiteAccount::class)->name('site.account');
});

// Simulateur de l'agrégateur (pilote « fake », jamais en production).
Route::get('/simulateur-paiement/{reference}', [PaymentSimulatorController::class, 'show'])->name('payments.simulator');
Route::post('/simulateur-paiement/{reference}', [PaymentSimulatorController::class, 'respond'])->name('payments.simulator.respond');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/espace', SpaceRedirectController::class)->name('space');
    Route::put('/preferences/theme', ThemePreferenceController::class)->name('preferences.theme');
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
                Route::get('evenements/{event}/annulation', [EventEditorController::class, 'disruption'])->name('events.disruption');
            });
            LivewirePage::route('compagnies', 'companies', 'admin.companies-table', 'Compagnies', Permission::CompaniesManage);
            LivewirePage::route('promoteurs', 'promoters', 'admin.promoters-table', 'Promoteurs', Permission::PromotersManage);
            LivewirePage::route('pass', 'passes', 'admin.passes-table', 'Pass & ventes', Permission::PassesManage);
            LivewirePage::route('remboursements', 'refunds', 'admin.refunds-table', 'Remboursements', Permission::PassesManage);
            LivewirePage::route('reversements', 'payouts', 'admin.payouts-table', 'Reversements', Permission::PayoutsManage);
            Route::middleware('can:'.Permission::PayoutsManage->value)->group(function () {
                Route::get('reversements/{payout}/releve.pdf', [PayoutDocumentController::class, 'statement'])->name('payouts.statement');
                Route::get('reversements/{payout}/justificatif', [PayoutDocumentController::class, 'proof'])->name('payouts.proof');
            });
            LivewirePage::route('lieux', 'venues', 'admin.venues-table', 'Lieux', Permission::EventsManage);
            LivewirePage::route('utilisateurs', 'users', 'admin.users-table', 'Utilisateurs & rôles', Permission::UsersManage);
            LivewirePage::route('journal', 'audit', 'admin.audit-log-table', 'Journal d\'audit', Permission::AuditView);
        });

        Route::prefix('compagnie')->name('company.')->middleware('space:company')->group(function () {
            Route::get('/', CompanyDashboardController::class)->name('dashboard');

            LivewirePage::route('evenements-proposes', 'proposed-events', 'company.proposed-events', 'Événements proposés', Permission::ProposedEventsRespond);
            LivewirePage::route('plan-de-transport', 'transport-plan', 'company.transport-plan', 'Plan de transport', Permission::OperationsManage);
            LivewirePage::route('exploitation-en-direct', 'live', 'company.live-operations', 'Exploitation en direct', Permission::OperationsManage);
            LivewirePage::route('pannes', 'breakdowns', 'company.breakdowns-table', 'Pannes & interventions', Permission::BreakdownsManage);
            LivewirePage::route('ventes', 'sales', 'company.sales-report', 'Ventes', Permission::SalesView);
            LivewirePage::route('reversements', 'payouts', 'company.payouts', 'Reversements', Permission::CompanyPayoutsView);
            Route::middleware('can:'.Permission::CompanyPayoutsView->value)->group(function () {
                Route::get('reversements/{payout}/releve.pdf', [PayoutDocumentController::class, 'statement'])->name('payouts.statement');
                Route::get('reversements/{payout}/justificatif', [PayoutDocumentController::class, 'proof'])->name('payouts.proof');
            });
            LivewirePage::route('affectations', 'assignments', 'company.assignments', 'Bus & affectations', Permission::FleetManage);
            LivewirePage::route('bus', 'assignments.fleet', 'company.buses-table', 'Flotte', Permission::FleetManage);
            LivewirePage::route('depots', 'depots', 'company.depots-table', 'Dépôts', Permission::FleetManage);
            LivewirePage::route('personnel', 'staff', 'company.staff-table', 'Chauffeurs & convoyeurs', Permission::StaffManage);
            LivewirePage::route('equipes-techniques', 'tech-teams', 'company.tech-teams-table', 'Équipes service technique', Permission::TechTeamsManage);
            LivewirePage::route('terminaux', 'devices', 'company.devices-table', 'PDA & terminaux', Permission::DevicesManage);

            LivewirePage::route('qualite', 'quality.overview', 'company.quality.overview', 'Qualité de service', Permission::QualityView);
            LivewirePage::route('qualite/avis', 'quality.reviews', 'company.quality.reviews', 'Avis des spectateurs', Permission::QualityView);
            LivewirePage::route('qualite/reclamations', 'quality.complaints', 'company.quality.complaints', 'Réclamations', Permission::ComplaintsManage);
            LivewirePage::route('qualite/ponctualite', 'quality.punctuality', 'company.quality.punctuality', 'Ponctualité', Permission::QualityView);
            LivewirePage::route('qualite/equipages', 'quality.crews', 'company.quality.crews', 'Équipages', Permission::QualityView);
            LivewirePage::route('qualite/bilans', 'quality.pilot', 'company.quality.pilot-review', 'Bilans du pilote', Permission::QualityView);
            Route::get('qualite/rapport/{event}', QualityReportController::class)->middleware('can:'.Permission::QualityView->value)->name('quality.report');
        });

        Route::prefix('promoteur')->name('promoter.')->middleware('space:promoter')->group(function () {
            LivewirePage::route('/', 'dashboard', 'promoter.tracking', 'Suivi de l\'événement', Permission::EventTracking);
            LivewirePage::route('evenement', 'event', 'promoter.event-sheet', 'Fiche événement', Permission::EventTracking);
            LivewirePage::route('zones-de-depose', 'drop-zones', 'promoter.drop-zones', 'Zones de dépose', Permission::EventTracking);
            LivewirePage::route('messages', 'messages', 'promoter.messages', 'Messages aux spectateurs', Permission::EventTracking);
            LivewirePage::route('bilan', 'report', 'promoter.report', 'Bilan', Permission::EventTracking);
            Route::get('bilan/{event}.pdf', PromoterReportController::class)->middleware('can:'.Permission::EventTracking->value)->name('report.pdf');
            Route::middleware('can:'.Permission::PromoKitView->value)->group(function () {
                Route::get('kit', [PromoKitController::class, 'index'])->name('kit');
                Route::get('kit/{event}/qr.svg', [PromoKitController::class, 'qr'])->name('kit.qr');
            });
        });
    });
});

if (app()->isLocal()) {
    // Boîte d'envoi des SMS simulés (codes OTP), uniquement en local.
    Route::get('/dev/sms', fn () => view('dev.sms', [
        'messages' => SmsMessage::query()->latest('id')->limit(50)->get(),
        'outbound' => OutboundMessage::query()->latest('id')->limit(30)->get(),
    ]))->name('dev.sms');
}
