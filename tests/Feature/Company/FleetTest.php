<?php

use App\Enums\BusStatus;
use App\Enums\Role;
use App\Livewire\Company\BusesTable;
use App\Livewire\Company\DepotsTable;
use App\Models\Bus;
use App\Models\Company;
use App\Models\Depot;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->actingAs(userWithRole(Role::Operations, ['company_id' => $this->company->id]));
});

it('ajoute un bus à la flotte de sa compagnie', function () {
    Livewire::test(BusesTable::class)
        ->call('create')
        ->set('number', '12')
        ->set('plate', '1234  ab 01')
        ->set('capacity', '50')
        ->call('save')
        ->assertHasNoErrors();

    $bus = Bus::query()->sole();

    expect($bus->company_id)->toBe($this->company->id)
        ->and($bus->plate)->toBe('1234 AB 01')
        ->and($bus->status)->toBe(BusStatus::InService);
});

it('refuse un numéro de parc déjà utilisé dans la compagnie', function () {
    Bus::factory()->for($this->company)->create(['number' => '12']);

    Livewire::test(BusesTable::class)
        ->call('create')
        ->set('number', '12')
        ->set('plate', 'NEW 01')
        ->call('save')
        ->assertHasErrors('number');
});

it('n\'affiche pas les bus d\'une autre compagnie et refuse de les modifier', function () {
    $foreign = Bus::factory()->create(['number' => '999']);

    Livewire::test(BusesTable::class)->assertDontSee('Bus 999');

    Livewire::test(BusesTable::class)->call('edit', $foreign->id)->assertNotFound();
});

it('refuse un dépôt d\'une autre compagnie', function () {
    $foreignDepot = Depot::factory()->create();

    Livewire::test(BusesTable::class)
        ->call('create')
        ->set('number', '1')
        ->set('plate', 'AA 1')
        ->set('depot_id', $foreignDepot->id)
        ->call('save')
        ->assertHasErrors('depot_id');
});

it('importe la flotte depuis un CSV', function () {
    Depot::factory()->for($this->company)->create(['name' => 'Dépôt de Yopougon']);
    $csv = "numero,immatriculation,capacite,gamme,statut,depot\n"
        ."12,1201 AB 01,50,Standard,En service,Dépôt de Yopougon\n"
        ."41,4101 AB 01,50,premium,Réserve,\n";

    Livewire::test(BusesTable::class)
        ->call('openImport')
        ->set('importFile', UploadedFile::fake()->createWithContent('flotte.csv', $csv))
        ->call('import')
        ->assertSet('importErrors', [])
        ->assertSet('showImport', false);

    expect(Bus::query()->count())->toBe(2)
        ->and(Bus::query()->where('number', '41')->sole()->status)->toBe(BusStatus::Reserve)
        ->and(Bus::query()->where('number', '12')->sole()->depot_id)->not->toBeNull();
});

it('n\'importe rien si une ligne est en erreur et signale les lignes', function () {
    $csv = "numero,immatriculation,capacite,gamme,statut,depot\n"
        ."12,1201 AB 01,50,Standard,En service,\n"
        ."13,1301 AB 01,5,Platine,En service,Inconnu\n";

    $component = Livewire::test(BusesTable::class)
        ->set('importFile', UploadedFile::fake()->createWithContent('flotte.csv', $csv))
        ->call('import');

    expect(Bus::query()->count())->toBe(0)
        ->and(implode(' ', $component->get('importErrors')))->toContain('Ligne 3');
});

it('crée un dépôt localisé', function () {
    Livewire::test(DepotsTable::class)
        ->call('create')
        ->set('name', 'Dépôt de Yopougon')
        ->set('latitude', '5.3364')
        ->set('longitude', '-4.0756')
        ->call('save')
        ->assertHasNoErrors();

    expect(Depot::query()->sole()->location)->not->toBeNull();
});
