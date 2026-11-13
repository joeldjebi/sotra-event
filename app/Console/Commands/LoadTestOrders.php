<?php

namespace App\Console\Commands;

use App\Enums\DropZoneUsage;
use App\Enums\EventStatus;
use App\Enums\Offer;
use App\Enums\PassStatus;
use App\Enums\PassType;
use App\Enums\TokenAbility;
use App\Models\Customer;
use App\Models\Event;
use App\Models\Order;
use App\Models\Pass;
use App\Models\Venue;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Test de charge de l'ouverture des ventes (NF-05, NF-06), contre un serveur qui tourne :
 * un événement de capacité limitée, des centaines de commandes simultanées par l'API, puis contrôle
 * qu'aucune place n'est vendue deux fois et mesure des temps de réponse (p50, p95).
 *
 * Exemple : PHP_CLI_SERVER_WORKERS=16 php artisan serve --port=8099, puis
 * php artisan loadtest:orders --url=http://127.0.0.1:8099 --orders=300 --capacity=250 --concurrency=25
 * Interdit en production : la commande crée des données de test (supprimées à la fin, sauf --keep).
 */
#[Signature('loadtest:orders {--url=http://127.0.0.1:8099 : Adresse du serveur testé} {--orders=300 : Commandes envoyées} {--capacity=250 : Places de l\'offre} {--concurrency=25 : Requêtes simultanées} {--customers=60 : Spectateurs distincts} {--keep : Conserver les données de test}')]
#[Description('Test de charge : commandes simultanées sans double vente, temps de réponse de l\'API')]
class LoadTestOrders extends Command
{
    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('Test de charge interdit en production.');

            return self::FAILURE;
        }

        $url = rtrim((string) $this->option('url'), '/');
        $orders = (int) $this->option('orders');
        $capacity = (int) $this->option('capacity');
        $concurrency = max(1, (int) $this->option('concurrency'));

        $event = $this->createEvent($capacity);
        $customers = Customer::factory()->count((int) $this->option('customers'))->create();
        $tokens = $customers
            ->map(fn (Customer $customer) => $customer->createToken('loadtest', [TokenAbility::Customer->value])->plainTextToken)
            ->values()->all();

        $this->info("Événement de test #{$event->id} : {$capacity} places, {$orders} commandes, {$concurrency} en parallèle.");

        $payload = [
            'event_id' => $event->id,
            'boarding_point_id' => $event->boardingPoints()->value('id'),
            'offer' => Offer::Standard->value,
            'pass_type' => PassType::RoundTrip->value,
            'quantity' => 1,
            'accept_terms' => true,
        ];

        $statuses = [];
        $orderTimes = [];
        $started = microtime(true);

        for ($sent = 0; $sent < $orders; $sent += $concurrency) {
            $batch = min($concurrency, $orders - $sent);
            $times = [];
            $responses = Http::pool(function (Pool $pool) use ($batch, $sent, $tokens, $url, $payload, &$times) {
                $requests = [];

                for ($i = 0; $i < $batch; $i++) {
                    $token = $tokens[($sent + $i) % count($tokens)];
                    $requests[] = $pool->as((string) $i)->withToken($token)->acceptJson()->timeout(30)
                        ->withOptions(['on_stats' => function ($stats) use (&$times, $i) {
                            $times[$i] = $stats->getTransferTime();
                        }])
                        ->post("{$url}/api/v1/orders", $payload);
                }

                return $requests;
            });

            foreach ($responses as $key => $response) {
                $status = $response instanceof Response ? $response->status() : 0;
                $statuses[$status] = ($statuses[$status] ?? 0) + 1;

                if (isset($times[(int) $key])) {
                    $orderTimes[] = $times[(int) $key] * 1000;
                }
            }
        }

        $elapsed = microtime(true) - $started;
        $readTimes = $this->measureReads($url, $event, $concurrency);

        $sold = Pass::query()->where('event_id', $event->id)->whereIn('status', PassStatus::holdingSeat())->count();
        $duplicates = (int) Pass::query()->where('event_id', $event->id)->whereNotNull('code')->value(DB::raw('count(code) - count(distinct code)'));

        $this->table(['Mesure', 'Valeur'], [
            ['Commandes envoyées', $orders],
            ['Débit', round($orders / max($elapsed, 0.001) * 60).' commandes / min'],
            ['Réponses', collect($statuses)->map(fn (int $count, int $status) => "{$status} × {$count}")->join(' · ')],
            ['Places occupées / capacité', "{$sold} / {$capacity}"],
            ['Commandes p50 / p95', $this->percentile($orderTimes, 50).' ms / '.$this->percentile($orderTimes, 95).' ms'],
            ['Lectures p50 / p95 (catalogue, détail)', $this->percentile($readTimes, 50).' ms / '.$this->percentile($readTimes, 95).' ms'],
        ]);

        $ok = $sold <= $capacity && $duplicates === 0 && ($statuses[500] ?? 0) === 0 && ($statuses[0] ?? 0) === 0;

        if (! $this->option('keep')) {
            $this->cleanUp($event, $customers->modelKeys());
        }

        $ok ? $this->info('Aucune double vente, aucune erreur serveur.') : $this->error('Échec : survente, doublon ou erreur serveur.');

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    private function createEvent(int $capacity): Event
    {
        $event = Event::factory()->create([
            'name' => 'Test de charge '.Str::upper(Str::random(4)),
            'venue_id' => Venue::factory()->create(['location' => Point::makeGeodetic(5.4925, -4.0791)])->id,
            'status' => EventStatus::OnSale,
            'channels' => ['web', 'app'],
            'starts_at' => now()->addDays(10)->setTime(18, 0),
            'ends_at' => now()->addDays(10)->setTime(23, 0),
        ]);
        $event->boardingPoints()->create([
            'commune' => 'Yopougon', 'commune_code' => 'YOP', 'place' => 'Gare Siporex', 'location' => Point::makeGeodetic(5.3446, -4.0802),
            'distance_km' => 12, 'zone' => 1, 'departure_at' => now()->addDays(10)->setTime(15, 45), 'return_departure_at' => now()->addDays(10)->setTime(23, 30),
        ]);
        $event->offers()->create(['offer' => Offer::Standard, 'bus_count' => 1, 'seats_per_bus' => $capacity]);
        $event->prices()->create(['zone' => 1, 'offer' => Offer::Standard, 'pass_type' => PassType::RoundTrip, 'price' => 3000]);
        $event->dropZones()->create(['name' => 'Parking', 'usage' => DropZoneUsage::DropOff, 'location' => Point::makeGeodetic(5.49, -4.07)]);

        return $event;
    }

    /**
     * @return list<float>
     */
    private function measureReads(string $url, Event $event, int $concurrency): array
    {
        $times = [];

        for ($round = 0; $round < 4; $round++) {
            Http::pool(function (Pool $pool) use ($url, $event, $concurrency, &$times) {
                $requests = [];

                for ($i = 0; $i < $concurrency; $i++) {
                    $path = $i % 2 === 0 ? '/api/v1/events' : "/api/v1/events/{$event->id}";
                    $requests[] = $pool->acceptJson()->withOptions(['on_stats' => function ($stats) use (&$times) {
                        $times[] = $stats->getTransferTime() * 1000;
                    }])->get($url.$path);
                }

                return $requests;
            });
        }

        return $times;
    }

    /**
     * @param  list<float>  $values
     */
    private function percentile(array $values, int $percent): int
    {
        if ($values === []) {
            return 0;
        }

        sort($values);

        return (int) round($values[(int) max(0, ceil($percent / 100 * count($values)) - 1)]);
    }

    /**
     * @param  list<int>  $customerIds
     */
    private function cleanUp(Event $event, array $customerIds): void
    {
        Pass::query()->where('event_id', $event->id)->delete();
        Order::query()->where('event_id', $event->id)->delete();
        $venueId = $event->venue_id;
        $event->delete();
        Venue::query()->whereKey($venueId)->delete();
        PersonalAccessToken::query()->where('tokenable_type', (new Customer)->getMorphClass())->whereIn('tokenable_id', $customerIds)->delete();
        Customer::query()->whereKey($customerIds)->delete();
        $this->line('Données de test supprimées.');
    }
}
