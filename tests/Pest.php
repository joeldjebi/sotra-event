<?php

use App\Actions\Orders\CreateOrder;
use App\Actions\Payments\ApplyPaymentResult;
use App\Actions\Payments\InitiatePayment;
use App\Enums\Direction;
use App\Enums\DropZoneUsage;
use App\Enums\EventStatus;
use App\Enums\Offer;
use App\Enums\PassType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Enums\SalesChannel;
use App\Enums\StaffRole;
use App\Models\Bus;
use App\Models\Customer;
use App\Models\Event;
use App\Models\Mission;
use App\Models\Order;
use App\Models\Pass;
use App\Models\Staff;
use App\Models\User;
use App\Models\Venue;
use App\Services\Operations\MissionPlanner;
use App\Services\Sales\PassTokenService;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/*
| Tous les tests Feature utilisent la base PostgreSQL de test, réinitialisée par transaction.
*/
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/**
 * Utilisateur web avec un rôle, 2FA déjà activée (sauf demande contraire).
 *
 * @param  array<string, mixed>  $attributes
 */
function userWithRole(Role $role, array $attributes = [], bool $withTwoFactor = true): User
{
    $factory = User::factory()->withRole($role);

    if ($withTwoFactor) {
        $factory = $factory->withTwoFactor();
    }

    return $factory->create($attributes);
}

/**
 * Événement en vente, prêt à vendre : un point (Yopougon, zone 1), offre Standard de $seats places,
 * grille aller-retour 3 000, aller simple et retour simple 2 000, canaux web et app.
 *
 * @param  array<string, mixed>  $attributes
 */
function eventOnSale(int $seats = 10, array $attributes = []): Event
{
    $event = Event::factory()->create([
        'venue_id' => Venue::factory()->create(['location' => Point::makeGeodetic(5.4925, -4.0791)])->id,
        'status' => EventStatus::OnSale,
        'channels' => ['web', 'app'],
        'starts_at' => '2026-11-15 18:00',
        'ends_at' => '2026-11-15 23:00',
    ] + $attributes);

    $event->boardingPoints()->create([
        'commune' => 'Yopougon', 'commune_code' => 'YOP', 'place' => 'Gare Siporex', 'location' => Point::makeGeodetic(5.3446, -4.0802),
        'distance_km' => 12, 'zone' => 1, 'departure_at' => '2026-11-15 15:45', 'return_departure_at' => '2026-11-15 23:30', 'travel_minutes' => 30,
    ]);
    $event->offers()->create(['offer' => Offer::Standard, 'bus_count' => 1, 'seats_per_bus' => $seats]);

    foreach ([[PassType::RoundTrip, 3000], [PassType::Outbound, 2000], [PassType::Return, 2000]] as [$type, $price]) {
        $event->prices()->create(['zone' => 1, 'offer' => Offer::Standard, 'pass_type' => $type, 'price' => $price]);
    }

    $event->dropZones()->create(['name' => 'Parking P5 · porte Est', 'usage' => DropZoneUsage::PickUp, 'location' => Point::makeGeodetic(5.49, -4.07)]);

    return $event->fresh(['boardingPoints', 'venue']);
}

/**
 * Commande payée de $quantity Pass sur l'événement.
 */
function paidOrder(Event $event, int $quantity, PassType $type = PassType::RoundTrip): Order
{
    $customer = Customer::factory()->create();
    $order = app(CreateOrder::class)->handle($customer, $event, $event->boardingPoints->first()->id, Offer::Standard, $type, $quantity, SalesChannel::Web);
    $payment = app(InitiatePayment::class)->handle($order, PaymentMethod::Wave, $customer->phone)['payment'];
    app(ApplyPaymentResult::class)->handle($payment, PaymentStatus::Succeeded);

    return $order->fresh('passes');
}

/**
 * Plan de transport d'un événement avec un bus, un chauffeur et un convoyeur par mission aller et retour.
 *
 * @return list<Mission>
 */
function staffedMissions(Event $event): array
{
    app(MissionPlanner::class)->plan($event);
    $missions = Mission::query()->where('event_id', $event->id)->orderBy('direction')->orderBy('sequence')->get();

    foreach ($missions as $index => $mission) {
        $mission->update([
            'bus_id' => Bus::factory()->create(['company_id' => $event->company_id, 'number' => (string) (20 + $index)])->id,
            'driver_id' => Staff::factory()->role(StaffRole::Driver)->create(['company_id' => $event->company_id])->id,
            'conveyor_id' => Staff::factory()->role(StaffRole::Conveyor)->create(['company_id' => $event->company_id])->id,
        ]);
    }

    return $missions->all();
}

/**
 * Scanne des Pass à la montée d'une mission, par l'API du convoyeur.
 *
 * @param  iterable<Pass>  $passes
 */
function boardMission(Mission $mission, iterable $passes): void
{
    Sanctum::actingAs(Staff::query()->findOrFail($mission->conveyor_id), ['conveyor']);

    foreach ($passes as $pass) {
        test()->postJson('/api/v1/scans', ['mission_id' => $mission->id, 'input' => app(PassTokenService::class)->sign($pass->fresh()), 'client_uuid' => (string) Str::uuid()])
            ->assertOk();
    }
}

/**
 * Départ confirmé par le convoyeur (CV-14), $minutes après l'heure prévue.
 */
function departAfter(Mission $mission, int $minutes): void
{
    test()->travelTo($mission->planned_departure_at->copy()->addMinutes($minutes));
    Sanctum::actingAs(Staff::query()->findOrFail($mission->conveyor_id), ['conveyor']);
    test()->postJson("/api/v1/missions/{$mission->id}/depart")->assertOk();
}

function outboundMissions(Event $event): array
{
    return Mission::query()->where('event_id', $event->id)->where('direction', Direction::Outbound)->orderBy('sequence')->get()->all();
}
