<?php

use App\Enums\Role;
use App\Livewire\Admin\CompaniesTable;
use App\Models\AuditLog;
use App\Models\Company;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(userWithRole(Role::SuperAdmin)));

it('crée une compagnie avec son compte de reversement chiffré (AD-08)', function () {
    Livewire::test(CompaniesTable::class)
        ->call('create')
        ->set('form.name', 'SOTRA')
        ->set('form.default_commission_rate', '10')
        ->set('form.payout_method', 'virement')
        ->set('form.bank_name', 'Banque Atlantique')
        ->set('form.account_holder', 'SOTRA SA')
        ->set('form.iban', 'ci93 ci000 01234')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showForm', false);

    $company = Company::query()->sole();

    expect($company->slug)->toBe('sotra')
        ->and($company->payout_details)->toBe(['bank_name' => 'Banque Atlantique', 'account_holder' => 'SOTRA SA', 'iban' => 'CI93CI00001234'])
        ->and((string) DB::table('companies')->value('payout_details'))->not->toContain('Banque Atlantique');
});

it('exige les coordonnées du mode de reversement choisi', function () {
    Livewire::test(CompaniesTable::class)
        ->call('create')
        ->set('form.name', 'SOTRA')
        ->set('form.payout_method', 'mobile_money')
        ->call('save')
        ->assertHasErrors(['form.mobile_money_operator', 'form.mobile_money_number']);
});

it('journalise le changement de compte de reversement sans exposer les coordonnées', function () {
    $company = Company::factory()->create(['payout_method' => 'virement', 'payout_details' => ['iban' => 'ANCIEN']]);

    Livewire::test(CompaniesTable::class)
        ->call('edit', $company->id)
        ->set('form.bank_name', 'Nouvelle banque')
        ->set('form.account_holder', 'SOTRA')
        ->set('form.iban', 'NOUVEAU')
        ->call('save')
        ->assertHasNoErrors();

    $log = AuditLog::query()->where('action', 'updated')->where('auditable_id', $company->id)->sole();

    expect($log->new_values['payout_details'])->toBe('••••••')
        ->and(json_encode($log->getAttributes()))->not->toContain('NOUVEAU');
});

it('désactive et réactive une compagnie', function () {
    $company = Company::factory()->create();

    Livewire::test(CompaniesTable::class)->call('toggleActive', $company->id);
    expect($company->fresh()->is_active)->toBeFalse();
});

it('refuse l\'accès à un rôle compagnie', function () {
    $this->actingAs(userWithRole(Role::Commercial));

    Livewire::test(CompaniesTable::class)->assertForbidden();
});
