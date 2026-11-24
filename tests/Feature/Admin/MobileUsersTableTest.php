<?php

use App\Enums\Role;
use App\Enums\StaffRole;
use App\Livewire\Admin\MobileUsersTable;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Staff;
use Livewire\Livewire;

it('liste les spectateurs avec leur mode de connexion, sans les comptes supprimés', function () {
    Customer::factory()->create(['phone' => '+2250707080839', 'first_name' => 'Awa', 'last_name' => 'Koné', 'password' => 'Abidjan2026']);
    Customer::factory()->create(['phone' => '+2250707080840', 'first_name' => 'Yao', 'last_name' => 'Kouassi']);
    Customer::factory()->create(['phone' => 'supprime-9', 'first_name' => null, 'anonymized_at' => now()]);

    $this->actingAs(userWithRole(Role::SuperAdmin));

    Livewire::test(MobileUsersTable::class)
        ->assertSee(['Awa Koné', '+2250707080839', 'Mot de passe', 'Yao Kouassi', 'Code SMS seul'])
        ->assertDontSee('supprime-9')
        ->set('search', '07 07 08 08 40')
        ->assertSee('Yao Kouassi')
        ->assertDontSee('Awa Koné');
});

it('liste les convoyeurs et techniciens de toutes les compagnies, sans les chauffeurs', function () {
    $sotra = Company::factory()->create(['name' => 'SOTRA']);
    $other = Company::factory()->create(['name' => 'Autre transporteur']);
    Staff::factory()->for($sotra)->role(StaffRole::Conveyor)->create(['first_name' => 'Moussa', 'last_name' => 'Bamba', 'password' => 'Initial2026', 'must_change_password' => true]);
    Staff::factory()->for($other)->role(StaffRole::Technician)->create(['first_name' => 'Ali', 'last_name' => 'Traoré']);
    Staff::factory()->for($sotra)->role(StaffRole::Driver)->create(['first_name' => 'Koffi', 'last_name' => 'Chauffeur']);

    $this->actingAs(userWithRole(Role::Operator));

    Livewire::test(MobileUsersTable::class)
        ->set('tab', 'personnel')
        ->assertSee(['Moussa Bamba', 'Mot de passe à changer', 'Ali Traoré', 'Sans mot de passe'])
        ->assertDontSee('Koffi Chauffeur')
        ->set('company', (string) $sotra->id)
        ->assertSee('Moussa Bamba')
        ->assertDontSee('Ali Traoré');
});

it('réserve la page au Super Admin et aux opérateurs', function (Role $role, int $status) {
    $this->actingAs(userWithRole($role))->get('/admin/utilisateurs-mobiles')->assertStatus($status);
})->with([
    'super admin' => [Role::SuperAdmin, 200],
    'opérateur' => [Role::Operator, 200],
    'centre d\'appel' => [Role::CallCenter, 403],
    'direction commerciale ACCES TRANS' => [Role::PlatformCommercial, 403],
    'administrateur SOTRA' => [Role::CompanyAdmin, 403],
]);
