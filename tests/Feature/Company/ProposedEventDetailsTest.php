<?php

use App\Enums\EventStatus;
use App\Enums\Role;
use App\Livewire\Company\ProposedEvents;
use App\Models\Event;
use Livewire\Livewire;

it('montre à la compagnie tout le détail de l\'événement proposé avant de décider', function () {
    $event = eventOnSale(attributes: ['status' => EventStatus::Proposed, 'description' => 'Finale de la coupe, 40 000 spectateurs attendus.', 'commission_rate' => '15.00']);
    $this->actingAs(userWithRole(Role::Commercial, ['company_id' => $event->company_id]));

    Livewire::test(ProposedEvents::class)
        ->call('show', $event->id)
        ->assertSee([
            'Finale de la coupe, 40 000 spectateurs attendus.',
            $event->venue->name,
            'Application mobile',
            'Gare Siporex', '12,0 km', '15:30', '15:45', '23:30',
            'Aller-retour', 'Aller simple', '3 000', '2 000',
            'Parking P5 · porte Est', 'Reprise retour',
            '1 bus × 10 places',
            '85 % du prix du Pass', '2 550 FCFA à la compagnie',
            $event->promoter->name,
        ]);
});

it('ne montre pas le détail d\'un événement d\'une autre compagnie ni d\'un brouillon', function () {
    $other = eventOnSale(attributes: ['status' => EventStatus::Proposed]);
    $draft = Event::factory()->create(['status' => EventStatus::Draft]);
    $this->actingAs(userWithRole(Role::Commercial, ['company_id' => $draft->company_id]));

    Livewire::test(ProposedEvents::class)->call('show', $other->id)->assertNotFound();
    Livewire::test(ProposedEvents::class)->call('show', $draft->id)->assertNotFound();
});
