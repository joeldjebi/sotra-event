<?php

use App\Enums\Direction;
use App\Enums\EventStatus;
use App\Enums\PassStatus;
use App\Enums\PassType;
use App\Models\Bus;
use App\Models\Mission;
use App\Services\Operations\MissionPlanner;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    // 5 bus de 10 places : capacité 50, on teste la répartition en plusieurs bus.
    $this->event = eventOnSale(seats: 10);
    $this->event->offers()->update(['bus_count' => 5]);
});

it('calcule les bus nécessaires par sens et affecte les Pass aller (RG-08)', function () {
    paidOrder($this->event, 4);
    paidOrder($this->event, 9);
    paidOrder($this->event, 3, PassType::Outbound);
    paidOrder($this->event, 2, PassType::Return);

    $result = app(MissionPlanner::class)->plan($this->event);

    // Aller : 4 + 9 + 3 = 16 Pass → 2 bus ; retour : 4 + 9 + 2 = 15 → 2 bus.
    expect(Mission::query()->where('direction', Direction::Outbound)->count())->toBe(2)
        ->and(Mission::query()->where('direction', Direction::Return)->count())->toBe(2)
        ->and($result['assigned'])->toBe(16)
        ->and($this->event->missions()->where('direction', Direction::Outbound)->first()->planned_departure_at->format('H:i'))->toBe('15:45');
});

it('garde les Pass d\'une même commande dans le même bus quand c\'est possible', function () {
    $first = paidOrder($this->event, 4);
    $group = paidOrder($this->event, 5);
    $last = paidOrder($this->event, 4);

    app(MissionPlanner::class)->plan($this->event);

    foreach ([$first, $group, $last] as $order) {
        expect($order->passes()->pluck('outbound_mission_id')->unique())->toHaveCount(1);
    }

    expect($first->passes()->value('outbound_mission_id'))->toBe($group->passes()->value('outbound_mission_id'))
        ->and($last->passes()->value('outbound_mission_id'))->not->toBe($first->passes()->value('outbound_mission_id'));
});

it('n\'affecte pas les Pass retour simple à une mission aller (RG-05)', function () {
    $order = paidOrder($this->event, 2, PassType::Return);

    app(MissionPlanner::class)->plan($this->event);

    expect($order->passes()->whereNotNull('outbound_mission_id')->count())->toBe(0)
        ->and(Mission::query()->where('direction', Direction::Outbound)->count())->toBe(0)
        ->and(Mission::query()->where('direction', Direction::Return)->count())->toBe(1);
});

it('actualise le plan sans perdre les affectations et affecte les ventes suivantes', function () {
    paidOrder($this->event, 8);
    $planner = app(MissionPlanner::class);
    $planner->plan($this->event);
    $mission = Mission::query()->where('direction', Direction::Outbound)->sole();
    $mission->update(['bus_id' => Bus::factory()->create(['company_id' => $this->event->company_id])->id]);

    // Vente après la génération : le Pass rejoint le bus existant dès le paiement.
    $late = paidOrder($this->event, 2);
    expect($late->passes()->pluck('outbound_mission_id')->unique()->all())->toBe([$mission->id]);

    // Une vente qui déborde crée un second bus.
    $overflow = paidOrder($this->event, 1);
    expect(Mission::query()->where('direction', Direction::Outbound)->count())->toBe(2)
        ->and($overflow->passes()->value('outbound_mission_id'))->not->toBe($mission->id);

    $planner->plan($this->event);
    expect($mission->fresh()->bus_id)->not->toBeNull()
        ->and(Mission::query()->where('direction', Direction::Outbound)->count())->toBe(2);
});

it('retire les missions vides et sans équipage quand la demande baisse', function () {
    $order = paidOrder($this->event, 10);
    paidOrder($this->event, 5);
    $planner = app(MissionPlanner::class);
    $planner->plan($this->event);
    expect(Mission::query()->where('direction', Direction::Return)->count())->toBe(2);

    $order->passes()->update(['status' => PassStatus::Cancelled, 'outbound_mission_id' => null]);
    $result = $planner->plan($this->event);

    expect(Mission::query()->where('direction', Direction::Return)->count())->toBe(1)
        ->and($result['removed'])->toBeGreaterThanOrEqual(1);
});

it('signale un besoin supérieur aux bus prévus par la compagnie', function () {
    $this->event->offers()->update(['bus_count' => 1, 'seats_per_bus' => 5]);
    paidOrder($this->event, 4);
    $this->event->offers()->update(['seats_per_bus' => 2]);

    $result = app(MissionPlanner::class)->plan($this->event->fresh());

    expect($result['warnings'])->toContain('Standard · Aller : 2 bus nécessaires pour 1 prévus.');
});

it('refuse de planifier un événement en brouillon', function () {
    $this->event->update(['status' => EventStatus::Draft]);

    expect(fn () => app(MissionPlanner::class)->plan($this->event))->toThrow(ValidationException::class);
});
