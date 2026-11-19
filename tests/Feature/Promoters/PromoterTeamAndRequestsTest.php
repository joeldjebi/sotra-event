<?php

use App\Enums\EventRequestStatus;
use App\Enums\EventStatus;
use App\Enums\Role;
use App\Livewire\Admin\EventRequestsTable;
use App\Livewire\Admin\Events\InformationStep;
use App\Livewire\Promoter\EventRequests;
use App\Livewire\Promoter\TeamTable;
use App\Models\Company;
use App\Models\Event;
use App\Models\EventRequest;
use App\Models\Promoter;
use App\Models\SmsMessage;
use App\Models\User;
use App\Models\Venue;
use App\Support\Navigation;
use Livewire\Livewire;

beforeEach(function () {
    $this->promoter = Promoter::factory()->create(['phone' => '+2250708091011']);
    $this->admin = userWithRole(Role::PromoterAdmin, ['promoter_id' => $this->promoter->id]);
});

it('laisse l\'administrateur du promoteur créer un chef de projet avec un mot de passe initial', function () {
    $this->actingAs($this->admin);
    $this->get('/promoteur/equipe')->assertOk();

    Livewire::test(TeamTable::class)
        ->call('create')
        ->set('name', 'Yao Chef')
        ->set('email', 'yao@promoteur.test')
        ->set('role', Role::TransportLead->value)
        ->set('initial_password', 'Provisoire2026')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('issuedCredentials.email', 'yao@promoteur.test');

    $member = User::query()->where('email', 'yao@promoteur.test')->sole();

    expect($member->promoter_id)->toBe($this->promoter->id)
        ->and($member->hasRole(Role::TransportLead->value))->toBeTrue()
        ->and($member->must_change_password)->toBeTrue();
});

it('exige un mot de passe initial et refuse un rôle hors de l\'espace promoteur', function () {
    $this->actingAs($this->admin);

    Livewire::test(TeamTable::class)
        ->call('create')
        ->set('name', 'Intrus')
        ->set('email', 'intrus@promoteur.test')
        ->set('role', Role::SuperAdmin->value)
        ->call('save')
        ->assertHasErrors(['role', 'initial_password']);
});

it('cloisonne l\'équipe : un autre promoteur et les chefs de projet n\'y ont pas accès', function () {
    $stranger = userWithRole(Role::TransportLead, ['promoter_id' => Promoter::factory()->create()->id]);
    $this->actingAs($this->admin);

    Livewire::test(TeamTable::class)->assertDontSee($stranger->email)->call('edit', $stranger->id)->assertNotFound();
    Livewire::test(TeamTable::class)->call('toggleActive', $stranger->id)->assertNotFound();
    expect($stranger->refresh()->is_active)->toBeTrue();

    Livewire::test(TeamTable::class)->call('edit', $this->admin->id)->set('role', Role::TransportLead->value)->call('save')->assertHasErrors('role');
    Livewire::test(TeamTable::class)->call('toggleActive', $this->admin->id)->assertForbidden();

    $lead = userWithRole(Role::TransportLead, ['promoter_id' => $this->promoter->id]);
    $this->actingAs($lead)->get('/promoteur/equipe')->assertForbidden();
    $this->get('/promoteur/demandes')->assertForbidden();
});

it('désactive un membre de son équipe', function () {
    $lead = userWithRole(Role::TransportLead, ['promoter_id' => $this->promoter->id]);
    $this->actingAs($this->admin);

    Livewire::test(TeamTable::class)->call('toggleActive', $lead->id);

    expect($lead->refresh()->is_active)->toBeFalse();
});

it('propose un événement, le modifie et le retire tant qu\'il est à l\'étude', function () {
    $this->actingAs($this->admin);
    $this->get('/promoteur/demandes')->assertOk();

    Livewire::test(EventRequests::class)
        ->call('create')
        ->set('name', 'Festival Gbairai')
        ->set('venue_name', 'Esplanade de Treichville')
        ->set('date', '2026-12-20')
        ->set('start_time', '19:00')
        ->set('end_time', '01:00')
        ->set('expected_attendance', '12000')
        ->set('desired_communes', 'Yopougon, Abobo')
        ->call('save')
        ->assertHasNoErrors();

    $request = EventRequest::query()->sole();

    expect($request->promoter_id)->toBe($this->promoter->id)
        ->and($request->submitted_by)->toBe($this->admin->id)
        ->and($request->status)->toBe(EventRequestStatus::Submitted)
        ->and($request->ends_at->toDateTimeString())->toBe('2026-12-21 01:00:00');

    Livewire::test(EventRequests::class)->call('edit', $request->id)->set('expected_attendance', '15000')->call('save');
    expect($request->refresh()->expected_attendance)->toBe(15000);

    Livewire::test(EventRequests::class)->call('withdraw', $request->id);
    expect($request->refresh()->status)->toBe(EventRequestStatus::Withdrawn);

    Livewire::test(EventRequests::class)->call('edit', $request->id)->assertForbidden();
});

it('refuse une demande dans le passé ou sans lieu', function () {
    $this->actingAs($this->admin);

    Livewire::test(EventRequests::class)
        ->call('create')
        ->set('name', 'Trop tard')
        ->set('date', '2026-11-01')
        ->call('save')
        ->assertHasErrors(['date', 'venue_name']);
});

it('ne montre pas les demandes d\'un autre promoteur', function () {
    $other = EventRequest::factory()->create(['name' => 'Demande concurrente']);
    $this->actingAs($this->admin);

    Livewire::test(EventRequests::class)->assertDontSee('Demande concurrente')->call('withdraw', $other->id)->assertNotFound();
});

it('crée l\'événement depuis une demande acceptée, préremplie dans la console', function () {
    $venue = Venue::factory()->create();
    $company = Company::factory()->create();
    $request = EventRequest::factory()->create([
        'promoter_id' => $this->promoter->id, 'submitted_by' => $this->admin->id, 'venue_id' => $venue->id, 'venue_name' => null, 'name' => 'Festival Gbairai',
    ]);

    $this->actingAs(userWithRole(Role::Operator));
    Livewire::test(EventRequestsTable::class)->assertSee('Festival Gbairai')
        ->call('accept', $request->id)
        ->assertRedirect(route('admin.events.create', ['demande' => $request->id]));

    $this->get(route('admin.events.create', ['demande' => $request->id]))->assertOk()->assertSee('Demande de '.$this->promoter->name);

    Livewire::test(InformationStep::class, ['eventRequestId' => $request->id])
        ->assertSet('name', 'Festival Gbairai')
        ->assertSet('promoter_id', $this->promoter->id)
        ->assertSet('venue_id', $venue->id)
        ->assertSet('date', '2026-12-20')
        ->assertSet('transport_lead_id', $this->admin->id)
        ->set('company_id', $company->id)
        ->call('save')
        ->assertHasNoErrors();

    $event = Event::query()->where('name', 'Festival Gbairai')->sole();

    expect($event->status)->toBe(EventStatus::Draft)
        ->and($event->promoter_id)->toBe($this->promoter->id)
        ->and($request->refresh()->status)->toBe(EventRequestStatus::Accepted)
        ->and($request->event_id)->toBe($event->id);
});

it('refuse une demande avec un motif envoyé au promoteur par SMS', function () {
    $request = EventRequest::factory()->create(['promoter_id' => $this->promoter->id, 'name' => 'Soirée trop proche']);
    $this->actingAs(userWithRole(Role::Operator));

    $table = Livewire::test(EventRequestsTable::class)->call('startRejection', $request->id);
    $table->call('reject')->assertHasErrors('reason');
    $table->set('reason', 'Date déjà prise par un autre événement.')->call('reject')->assertHasNoErrors();

    expect($request->refresh()->status)->toBe(EventRequestStatus::Rejected)
        ->and(SmsMessage::query()->where('to', '+2250708091011')->latest('id')->value('body'))->toContain('Date déjà prise');

    $this->actingAs($this->admin);
    Livewire::test(EventRequests::class)->assertSee('Refusée')->assertSee('Date déjà prise');
});

it('ajoute « Ma structure » au menu de l\'administrateur du promoteur seulement', function () {
    $labels = fn (User $user) => collect(Navigation::for($user))->flatMap(fn ($section) => array_column($section['items'], 'label'))->all();

    expect($labels($this->admin))->toContain('Demandes d\'événement', 'Mon équipe', 'Dossier')
        ->and($labels(userWithRole(Role::TransportLead, ['promoter_id' => $this->promoter->id])))->not->toContain('Mon équipe')
        ->and($labels(userWithRole(Role::Operator)))->toContain('Demandes des promoteurs');
});
