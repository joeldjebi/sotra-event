<?php

use App\Enums\Role;
use App\Livewire\Admin\CompaniesTable;
use App\Livewire\Company\Assignments;
use App\Livewire\Company\BusesTable;
use App\Livewire\Company\DevicesTable;
use App\Livewire\Company\ProposedEvents;
use App\Livewire\Company\StaffTable;
use App\Livewire\Company\TeamTable;
use App\Livewire\Company\TransportPlan;
use App\Models\Bus;
use App\Models\Company;
use App\Models\User;
use Livewire\Livewire;

/*
| Équipes SOTRA : le super admin crée l'administrateur SOTRA, qui crée les comptes de ses directions.
| Direction générale : tout, en consultation seule. Direction commerciale : ventes et événements.
| Direction de l'exploitation : les bus et le transport des passagers, qualité de service comprise.
*/

beforeEach(function () {
    $this->company = Company::factory()->create(['name' => 'SOTRA']);
});

it('laisse le super admin créer l\'administrateur SOTRA', function () {
    $this->actingAs(userWithRole(Role::SuperAdmin));

    Livewire::test(CompaniesTable::class)
        ->call('createAdmin', $this->company->id)
        ->set('admin_name', 'Awa Koné')
        ->set('admin_email', 'Awa.Kone@sotra.ci')
        ->call('generatePassword', 'admin_password')
        ->call('saveAdmin')
        ->assertHasNoErrors()
        ->assertSet('issuedCredentials.email', 'awa.kone@sotra.ci')
        ->assertSee('Awa Koné');

    $admin = User::query()->where('email', 'awa.kone@sotra.ci')->sole();

    expect($admin->company_id)->toBe($this->company->id)
        ->and($admin->hasRole(Role::CompanyAdmin->value))->toBeTrue()
        ->and($admin->must_change_password)->toBeTrue();
});

it('laisse l\'administrateur SOTRA créer les comptes de ses directions, et d\'elles seules', function () {
    $admin = userWithRole(Role::CompanyAdmin, ['company_id' => $this->company->id]);
    $other = userWithRole(Role::Commercial);
    $this->actingAs($admin);

    $this->get('/compagnie/equipe')->assertOk()->assertSee('Équipe SOTRA');

    Livewire::test(TeamTable::class)
        ->assertDontSee($other->email)
        ->call('create')
        ->set('name', 'Directeur Général')
        ->set('email', 'dg@sotra.ci')
        ->set('role', Role::GeneralManagement->value)
        ->call('generatePassword', 'initial_password')
        ->call('save')
        ->assertHasNoErrors();

    $director = User::query()->where('email', 'dg@sotra.ci')->sole();
    expect($director->company_id)->toBe($this->company->id)
        ->and($director->hasRole(Role::GeneralManagement->value))->toBeTrue();

    // Ni rôle de la console, ni compte d'une autre compagnie, ni ses propres droits retirés.
    Livewire::test(TeamTable::class)->call('create')->set('name', 'X')->set('email', 'x@sotra.ci')
        ->set('role', Role::SuperAdmin->value)->set('initial_password', 'Abcdefgh12345')->call('save')->assertHasErrors('role');
    Livewire::test(TeamTable::class)->call('edit', $other->id)->assertNotFound();
    Livewire::test(TeamTable::class)->call('edit', $admin->id)->set('role', Role::Commercial->value)->call('save')->assertHasErrors('role');
});

it('ouvre à chaque direction SOTRA ses seuls écrans', function (Role $role, array $allowed, array $forbidden) {
    $this->actingAs(userWithRole($role, ['company_id' => $this->company->id]));

    foreach ($allowed as $path) {
        $this->get($path)->assertOk();
    }

    foreach ($forbidden as $path) {
        $this->get($path)->assertForbidden();
    }
})->with([
    'direction générale' => [Role::GeneralManagement,
        ['/compagnie', '/compagnie/ventes', '/compagnie/evenements-proposes', '/compagnie/reversements', '/compagnie/plan-de-transport', '/compagnie/exploitation-en-direct',
            '/compagnie/affectations', '/compagnie/bus', '/compagnie/depots', '/compagnie/personnel', '/compagnie/pannes', '/compagnie/equipes-techniques',
            '/compagnie/terminaux', '/compagnie/qualite', '/compagnie/qualite/reclamations'],
        ['/compagnie/equipe', '/admin']],
    'direction commerciale' => [Role::Commercial,
        ['/compagnie/ventes', '/compagnie/evenements-proposes', '/compagnie/reversements'],
        ['/compagnie/plan-de-transport', '/compagnie/bus', '/compagnie/personnel', '/compagnie/qualite', '/compagnie/equipe']],
    'direction de l\'exploitation' => [Role::Operations,
        ['/compagnie/plan-de-transport', '/compagnie/exploitation-en-direct', '/compagnie/affectations', '/compagnie/bus', '/compagnie/personnel',
            '/compagnie/pannes', '/compagnie/terminaux', '/compagnie/qualite', '/compagnie/qualite/reclamations'],
        ['/compagnie/ventes', '/compagnie/evenements-proposes', '/compagnie/reversements', '/compagnie/equipe']],
    'administrateur' => [Role::CompanyAdmin,
        ['/compagnie/equipe', '/compagnie/ventes', '/compagnie/bus', '/compagnie/qualite/reclamations'],
        ['/admin']],
]);

it('laisse la direction générale tout consulter sans rien modifier', function () {
    $this->actingAs(userWithRole(Role::GeneralManagement, ['company_id' => $this->company->id]));
    $bus = Bus::factory()->create(['company_id' => $this->company->id, 'number' => '742']);

    Livewire::test(BusesTable::class)
        ->assertSee('742')
        ->assertDontSee('wire:click="create"', false)
        ->assertDontSee('wire:click="delete', false);

    Livewire::test(BusesTable::class)->call('create')->assertForbidden();
    Livewire::test(BusesTable::class)->call('delete', $bus->id)->assertForbidden();
    Livewire::test(BusesTable::class)->call('openImport')->assertForbidden();
    Livewire::test(StaffTable::class)->call('create')->assertForbidden();
    Livewire::test(DevicesTable::class)->call('create')->assertForbidden();
    Livewire::test(TransportPlan::class)->call('generate', 1)->assertForbidden();
    Livewire::test(Assignments::class)->call('removeReserve', 1)->assertForbidden();
    Livewire::test(ProposedEvents::class)->call('accept', 1)->assertForbidden();

    expect(Bus::query()->whereKey($bus->id)->exists())->toBeTrue();
});
