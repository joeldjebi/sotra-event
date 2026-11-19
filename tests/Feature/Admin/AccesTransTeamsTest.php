<?php

use App\Enums\CallReason;
use App\Enums\PassStatus;
use App\Enums\PromoterStatus;
use App\Enums\Role;
use App\Livewire\Admin\CallCenter;
use App\Livewire\Admin\DevicesOverview;
use App\Livewire\Admin\EventRequestsTable;
use App\Livewire\Admin\OperationsMonitor;
use App\Livewire\Admin\PayoutsTable;
use App\Livewire\Admin\PromotersTable;
use App\Livewire\Admin\SalesOverview;
use App\Models\CallLog;
use App\Models\Complaint;
use App\Models\Device;
use App\Models\EventRequest;
use App\Models\Promoter;
use App\Models\SmsMessage;
use Livewire\Livewire;

/*
| Équipes ACCES TRANS : direction commerciale (consultation), direction technique (supervision sans montant),
| centre d'appel (fiche spectateur).
*/

it('ouvre à chaque équipe ses seuls écrans, et sa première page après connexion', function (Role $role, string $home, array $allowed, array $forbidden) {
    $this->actingAs(userWithRole($role));

    $this->get(route('space'))->assertRedirect($home);

    foreach ($allowed as $path) {
        $this->get($path)->assertOk();
    }

    foreach ($forbidden as $path) {
        $this->get($path)->assertForbidden();
    }
})->with([
    'direction commerciale' => [Role::PlatformCommercial, '/admin', ['/admin', '/admin/ventes', '/admin/evenements', '/admin/promoteurs', '/admin/demandes-evenements', '/admin/reversements'],
        ['/admin/evenements/nouveau', '/admin/utilisateurs', '/admin/pass', '/admin/compagnies', '/admin/supervision', '/admin/centre-appel']],
    'direction technique' => [Role::PlatformTechnical, '/admin/supervision', ['/admin/supervision', '/admin/terminaux'],
        ['/admin', '/admin/ventes', '/admin/reversements', '/admin/evenements', '/admin/utilisateurs', '/admin/centre-appel']],
    'centre d\'appel' => [Role::CallCenter, '/admin/centre-appel', ['/admin/centre-appel'],
        ['/admin', '/admin/ventes', '/admin/supervision', '/admin/reversements', '/admin/utilisateurs', '/admin/pass']],
]);

it('laisse la direction commerciale consulter sans rien modifier', function () {
    $this->actingAs(userWithRole(Role::PlatformCommercial));
    $promoter = Promoter::factory()->create(['status' => PromoterStatus::Pending]);
    $request = EventRequest::factory()->create();
    $event = eventOnSale();
    paidOrder($event, 2);

    Livewire::test(PromotersTable::class)->assertSee($promoter->name)->assertDontSee('wire:click="create"', false)
        ->call('review', $promoter->id)->assertSee('Justificatifs')
        ->call('approve')->assertForbidden();
    expect($promoter->refresh()->status)->toBe(PromoterStatus::Pending);

    Livewire::test(PromotersTable::class)->call('create')->assertForbidden();
    Livewire::test(EventRequestsTable::class)->assertSee($request->name)->assertDontSee('Créer l\'événement')->call('accept', $request->id)->assertForbidden();
    Livewire::test(PayoutsTable::class)->assertOk();
    Livewire::test(SalesOverview::class)->assertSet('eventId', $event->id)->assertSee('6 000');

    $this->get('/admin/evenements')->assertOk()->assertDontSee('Nouvel événement')->assertSee(route('admin.sales', ['evenement' => $event->id]), false);
});

it('donne à la direction technique la supervision de toutes les compagnies, sans montant', function () {
    $this->actingAs(userWithRole(Role::PlatformTechnical));
    $event = eventOnSale();
    paidOrder($event, 2);
    staffedMissions($event);
    Device::factory()->create(['label' => 'PDA Yopougon 1']);

    Livewire::test(OperationsMonitor::class)->assertSet('eventId', $event->id)->assertSee('Aucune panne en cours')
        ->set('tab', 'transport')->assertSee('Yopougon')->assertDontSee('FCFA');
    Livewire::test(DevicesOverview::class)->assertSee('PDA Yopougon 1');
});

it('retrouve un spectateur au téléphone, renvoie son Pass, ouvre une réclamation et note l\'appel', function () {
    $this->actingAs($agent = userWithRole(Role::CallCenter));
    $event = eventOnSale();
    $order = paidOrder($event, 1);
    $pass = $order->passes->first()->fresh();
    $phone = $pass->holder->phone;

    $component = Livewire::test(CallCenter::class)
        ->set('query', $phone)->call('search')
        ->assertSee($pass->code)->assertSee($event->name);

    $sent = SmsMessage::query()->count();
    $component->call('resend', $pass->id);
    expect(SmsMessage::query()->count())->toBe($sent + 1);

    $component->call('startAction', $pass->id, 'complaint')
        ->set('complaintCategory', 'retard')->set('complaintDetails', 'Le spectateur signale un départ très en retard.')
        ->call('openComplaint')->assertHasNoErrors();
    expect(Complaint::query()->where('pass_id', $pass->id)->count())->toBe(1);

    $component->set('callReason', CallReason::Information->value)->set('callNotes', 'Horaires de retour confirmés.')->call('logCall');

    expect(CallLog::query()->where('phone', $phone)->pluck('reason')->map->value->all())
        ->toContain('pass_non_recu', 'reclamation', 'information')
        ->and(CallLog::query()->where('user_id', $agent->id)->count())->toBe(3);

    // Recherche aussi par code de Pass ; l'adresse de la page ne contient jamais le numéro.
    Livewire::test(CallCenter::class)->set('query', $pass->code)->call('search')->assertSet('customerId', $pass->holder_id);
});

it('annule un Pass selon les règles du spectateur depuis le centre d\'appel', function () {
    $this->actingAs(userWithRole(Role::CallCenter));
    $pass = paidOrder(eventOnSale(), 1)->passes->first()->fresh();

    Livewire::test(CallCenter::class)
        ->set('query', $pass->holder->phone)->call('search')
        ->call('startAction', $pass->id, 'cancel')
        ->set('cancelNote', 'Empêchement familial')->call('cancel')->assertHasNoErrors();

    expect($pass->refresh()->status)->toBeIn([PassStatus::Cancelled, PassStatus::Refunded])
        ->and($pass->refund->reason->value)->toBe('client');
});

it('refuse d\'agir sur le Pass d\'un autre spectateur', function () {
    $this->actingAs(userWithRole(Role::CallCenter));
    $event = eventOnSale();
    $mine = paidOrder($event, 1)->passes->first()->fresh();
    $other = paidOrder($event, 1)->passes->first()->fresh();

    Livewire::test(CallCenter::class)->set('query', $mine->holder->phone)->call('search')
        ->call('resend', $other->id)->assertNotFound();
});
