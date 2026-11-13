<?php

use App\Enums\Role;
use App\Livewire\Admin\PassesTable;
use App\Models\Customer;
use Livewire\Livewire;

beforeEach(function () {
    $this->event = eventOnSale();
    $this->order = paidOrder($this->event, 1);
    [$this->outbound] = staffedMissions($this->event);
    $this->pass = $this->order->passes->first()->fresh();
});

it('retrouve un Pass par code ou téléphone et affiche son historique de scans (AD-10)', function () {
    boardMission($this->outbound, [$this->pass]);
    $other = paidOrder($this->event, 1)->passes->first();
    $this->actingAs(userWithRole(Role::Operator));

    Livewire::test(PassesTable::class)
        ->set('search', $this->pass->code)
        ->assertSee($this->pass->code)
        ->assertDontSee($other->code)
        ->set('search', $this->pass->holder->phone)
        ->assertSee($this->pass->code)
        ->call('show', $this->pass->id)
        ->assertSee('Historique des scans')
        ->assertSee('Aller · Bus 20');
});

it('transfère un Pass à la demande de son détenteur : l\'ancien QR devient invalide', function () {
    $this->actingAs(userWithRole(Role::SuperAdmin));

    Livewire::test(PassesTable::class)
        ->call('show', $this->pass->id)
        ->set('transferPhone', '0707070707')
        ->call('transfer')
        ->assertHasNoErrors();

    expect($this->pass->fresh())
        ->holder_id->toBe(Customer::query()->where('phone', '+2250707070707')->value('id'))
        ->token_version->toBe(2);
});

it('réserve Pass & ventes à la console ACCES EVENT', function () {
    $this->actingAs(userWithRole(Role::Commercial, ['company_id' => $this->event->company_id]))->get('/admin/pass')->assertForbidden();
    $this->actingAs(userWithRole(Role::Operator))->get('/admin/pass')->assertOk()->assertSee('Pass &amp; ventes', false);
});
