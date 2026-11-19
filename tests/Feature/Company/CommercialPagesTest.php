<?php

use App\Enums\Role;
use App\Livewire\Company\Demand;
use App\Livewire\Company\Pricing;
use App\Livewire\Company\Reports;
use App\Models\User;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;

/*
| Direction commerciale : offres et grille (consultation), demande par commune, rapports à télécharger.
*/

beforeEach(function () {
    $this->event = eventOnSale(seats: 10);
    $this->actingAs(userWithRole(Role::Commercial, ['company_id' => $this->event->company_id]));
});

it('affiche les offres et la grille de l\'événement, sans pouvoir la modifier', function () {
    $this->get('/compagnie/offres-et-grille')->assertOk();

    Livewire::test(Pricing::class)
        ->assertSet('eventId', $this->event->id)
        ->assertSee('Grille tarifaire')
        ->assertSee('Zone 1')
        ->assertSee('3 000')
        ->assertSee('Yopougon')
        ->assertDontSee('wire:submit', false);
});

it('répartit la demande par commune, avec les places prévues', function () {
    paidOrder($this->event, 3);
    staffedMissions($this->event);

    $this->get('/compagnie/demande-par-commune')->assertOk();

    Livewire::test(Demand::class)
        ->assertSee('Yopougon')
        ->assertViewHas('demand', fn (array $demand): bool => $demand['rows'][0]['outbound'] === 3
            && $demand['rows'][0]['return'] === 3
            && $demand['rows'][0]['by_offer']['standard'] === 3
            && $demand['rows'][0]['seats'] > 0
            && $demand['totals']['revenue'] === 9000);
});

it('ne mélange pas la demande des autres compagnies', function () {
    paidOrder(eventOnSale(), 2);

    Livewire::test(Demand::class)
        ->assertViewHas('demand', fn (array $demand): bool => $demand['rows'] === []);
});

it('liste les rapports et télécharge les ventes en Excel, pour ses seuls événements', function () {
    Excel::fake();
    paidOrder($this->event, 2);
    $other = eventOnSale();

    Livewire::test(Reports::class)
        ->assertSee($this->event->name)
        ->assertDontSee($other->name)
        ->assertSee('Ventes (Excel)')
        ->assertDontSee('Qualité (PDF)');

    $this->get(route('company.reports.sales', $this->event))->assertOk();
    $this->get(route('company.reports.sales', $other))->assertNotFound();
});

it('réserve ces pages à la direction commerciale, à la direction générale et à l\'administrateur', function () {
    $this->actingAs(userWithRole(Role::Operations, ['company_id' => $this->event->company_id]));

    foreach (['/compagnie/offres-et-grille', '/compagnie/demande-par-commune', '/compagnie/rapports'] as $path) {
        $this->get($path)->assertForbidden();
    }

    $this->actingAs(userWithRole(Role::GeneralManagement, ['company_id' => $this->event->company_id]));

    foreach (['/compagnie/offres-et-grille', '/compagnie/demande-par-commune', '/compagnie/rapports'] as $path) {
        $this->get($path)->assertOk();
    }
});

it('calcule les initiales sans les étiquettes entre crochets', function () {
    expect((new User(['name' => '[TEST] Admin SOTRA']))->initials())->toBe('AS')
        ->and((new User(['name' => 'Awa Koné']))->initials())->toBe('AK');
});
