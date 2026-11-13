<?php

namespace Database\Seeders;

use App\Enums\DropZoneUsage;
use App\Enums\EventStatus;
use App\Enums\NegotiationSubject;
use App\Enums\Offer;
use App\Enums\PassType;
use App\Models\Company;
use App\Models\Event;
use App\Models\EventNegotiation;
use App\Models\Promoter;
use App\Models\User;
use App\Models\Venue;
use App\Services\ZoneResolver;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Événements de démonstration (données d'exemple des maquettes, coordonnées approximatives) :
 * un concert en vente (site spectateur), un match proposé à la compagnie, un brunch en brouillon.
 */
class DemoEventsSeeder extends Seeder
{
    public function run(ZoneResolver $zones): void
    {
        $sotra = Company::query()->where('slug', 'sotra')->firstOrFail();
        $promoter = Promoter::query()->where('name', 'Promoteur Démo')->firstOrFail();
        $lead = User::query()->where('email', 'chef.projet@promoteur.test')->first();
        $admin = User::query()->where('email', 'admin@eventtrans.test')->first();
        $ebimpe = Venue::query()->where('name', 'Stade Olympique d\'Ebimpé')->firstOrFail();
        $fhb = Venue::query()->updateOrCreate(['name' => 'Stade Félix Houphouët-Boigny'], ['address' => 'Plateau', 'city' => 'Abidjan', 'location' => Point::makeGeodetic(5.3266, -4.0189)]);
        $angre = Venue::query()->updateOrCreate(['name' => 'Stade d\'Angré'], ['address' => 'Angré, Cocody', 'city' => 'Abidjan', 'location' => Point::makeGeodetic(5.3950, -3.9910)]);

        $concertDay = Carbon::parse('2026-11-15');

        $concert = $this->event('Concert au stade d\'Ebimpé', $ebimpe, $concertDay->copy()->setTime(18, 0), $sotra, $promoter, $lead, $admin, EventStatus::OnSale, 3000, true);
        $this->points($concert, $zones, [
            ['YOP', 'Gare Siporex', 5.3446, -4.0802, '15:30'],
            ['ABO', 'Gare d\'Abobo', 5.4180, -4.0200, '15:45'],
            ['COC', 'Carrefour Riviera 2', 5.3600, -3.9620, '15:30'],
            ['MAR', 'Place Marcory Zone 4', 5.3030, -3.9870, '15:00'],
            ['PLA', 'Gare Sud', 5.3170, -4.0170, '15:15'],
        ], '23:30');
        $this->grid($concert, [Offer::Standard, Offer::Premium, Offer::Gold], [1000, 400, 50]);
        $concert->dropZones()->delete();
        $concert->dropZones()->createMany([
            ['name' => 'Parking P3 · porte Nord', 'usage' => DropZoneUsage::DropOff, 'location' => Point::makeGeodetic(5.4950, -4.0780), 'radius_m' => 200],
            ['name' => 'Parking P5 · porte Est', 'usage' => DropZoneUsage::PickUp, 'scheduled_at' => $concertDay->copy()->setTime(23, 30), 'location' => Point::makeGeodetic(5.4915, -4.0760), 'radius_m' => 200],
            ['name' => 'Accueil EVENT TRANS · stand près de P3', 'usage' => DropZoneUsage::Welcome, 'location' => Point::makeGeodetic(5.4948, -4.0785), 'radius_m' => 50],
        ]);
        $this->thread($concert, $admin, $sotra);

        $match = $this->event('Match FIF · Ligue 1', $fhb, Carbon::parse('2026-11-22 16:00'), $sotra, $promoter, $lead, $admin, EventStatus::Proposed, 2000);
        $this->points($match, $zones, [
            ['YOP', 'Gare Siporex', 5.3446, -4.0802, '14:00'],
            ['ABO', 'Gare d\'Abobo', 5.4180, -4.0200, '14:00'],
        ], '18:30');
        $this->grid($match, [Offer::Standard], [null]);

        $brunch = $this->event('LE MEGA BRUNCH D\'ABIDJAN', $angre, Carbon::parse('2026-12-12 11:00'), $sotra, $promoter, $lead, $admin, EventStatus::Draft, 800);
        $brunch->update(['ends_at' => Carbon::parse('2026-12-12 18:00')]);
    }

    private function event(string $name, Venue $venue, Carbon $startsAt, Company $company, Promoter $promoter, ?User $lead, ?User $admin, EventStatus $status, int $estimated, bool $featured = false): Event
    {
        return Event::query()->withoutGlobalScopes()->updateOrCreate(['name' => $name], [
            'slug' => Str::slug($name).'-'.$startsAt->format('Y-m-d'),
            'venue_id' => $venue->id,
            'promoter_id' => $promoter->id,
            'transport_lead_id' => $lead?->id,
            'company_id' => $company->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->setTime(23, 0),
            'doors_open_at' => $startsAt->copy()->subHour(),
            'estimated_passes' => $estimated,
            'commission_rate' => 10,
            'fee_rate' => 2,
            'channels' => ['web', 'app', 'whatsapp'],
            'status' => $status,
            'featured' => $featured,
            'created_by' => $admin?->id,
            'proposed_at' => $status === EventStatus::Draft ? null : now(),
            'accepted_at' => in_array($status, [EventStatus::Accepted, EventStatus::OnSale], true) ? now() : null,
            'sale_opened_at' => $status === EventStatus::OnSale ? now() : null,
        ]);
    }

    /**
     * @param  list<array{0: string, 1: string, 2: float, 3: float, 4: string}>  $points
     */
    private function points(Event $event, ZoneResolver $zones, array $points, string $returnTime): void
    {
        $event->boardingPoints()->delete();
        $day = $event->starts_at->toDateString();

        foreach ($points as $index => [$code, $place, $lat, $lng, $departure]) {
            $location = Point::makeGeodetic($lat, $lng);
            $distance = $zones->distanceKm($event->venue->location, $location);
            $return = Carbon::parse("{$day} {$returnTime}");

            $event->boardingPoints()->create([
                'commune' => config('eventtrans.communes')[$code],
                'commune_code' => $code,
                'place' => $place,
                'location' => $location,
                'distance_km' => $distance,
                'zone' => $zones->zoneFor($distance),
                'departure_at' => Carbon::parse("{$day} {$departure}"),
                'return_departure_at' => $return->lte(Carbon::parse("{$day} {$departure}")) ? $return->addDay() : $return,
                'travel_minutes' => $zones->estimatedTravelMinutes($distance),
                'position' => $index + 1,
            ]);
        }
    }

    /**
     * @param  list<Offer>  $offers
     * @param  list<int|null>  $busCounts
     */
    private function grid(Event $event, array $offers, array $busCounts): void
    {
        $event->offers()->delete();
        $event->prices()->delete();
        $defaults = (array) config('eventtrans.events.default_price_grid');

        foreach ($offers as $index => $offer) {
            $event->offers()->create([
                'offer' => $offer,
                'bus_count' => $busCounts[$index] === null ? null : max(1, intdiv($busCounts[$index], 50) + 1),
                'seats_per_bus' => $busCounts[$index] === null ? null : 50,
            ]);

            foreach (PassType::cases() as $type) {
                foreach (ZoneResolver::zones() as $zone) {
                    $price = $defaults[$type->value][$zone][$offer->value] ?? null;

                    if ($price !== null) {
                        $event->prices()->create(['zone' => $zone, 'offer' => $offer, 'pass_type' => $type, 'price' => $price]);
                    }
                }
            }
        }
    }

    private function thread(Event $event, ?User $admin, Company $company): void
    {
        $event->negotiations()->delete();
        $commercial = User::query()->where('email', 'commercial@sotra.test')->first();

        $event->negotiations()->createMany([
            ['user_id' => $admin?->id, 'side' => EventNegotiation::SIDE_ACCES_EVENT, 'subject' => NegotiationSubject::Proposal, 'message' => 'Concert de 60 000 places, 3 000 Pass estimés sur 5 points.'],
            ['user_id' => $commercial?->id, 'side' => EventNegotiation::SIDE_COMPANY, 'subject' => NegotiationSubject::Acceptance, 'message' => "{$company->name} confirme sa participation."],
        ]);
    }
}
