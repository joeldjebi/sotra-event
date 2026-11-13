<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Sonde de supervision (NF-08), à interroger chaque minute par l'outil de surveillance :
 * base de données, cache Redis, stockage, retard de la file et dernier passage du planificateur.
 * Répond 503 si un élément critique est en panne. Aucune donnée sensible n'est exposée.
 */
#[Group('Supervision', 'Sonde de disponibilité de la plateforme, pour la surveillance.', weight: 10)]
class HealthController extends Controller
{
    public const string SCHEDULER_HEARTBEAT = 'health:scheduler-heartbeat';

    /**
     * État de la plateforme : base, cache, stockage, file d'attente et planificateur (200 si tout va bien, 503 sinon).
     */
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->check(fn () => DB::scalar('select 1') === 1),
            'cache' => $this->check(function () {
                Cache::put('health:ping', 'pong', 10);

                return Cache::get('health:ping') === 'pong';
            }),
            'storage' => $this->check(fn () => Storage::disk('local')->put('health/ping', (string) now()->timestamp)),
        ];

        $backlog = $this->check(fn () => Queue::size() <= (int) config('eventtrans.ops.max_queue_backlog'));
        $heartbeat = Cache::get(self::SCHEDULER_HEARTBEAT);
        $scheduler = is_int($heartbeat) && now()->timestamp - $heartbeat <= 180;

        $healthy = ! in_array(false, $checks, true);

        return response()->json([
            'status' => $healthy ? 'ok' : 'down',
            'checks' => $checks + ['queue' => $backlog, 'scheduler' => $scheduler],
            'time' => now()->toIso8601String(),
        ], $healthy ? 200 : 503);
    }

    /**
     * @param  callable(): bool  $probe
     */
    private function check(callable $probe): bool
    {
        try {
            return (bool) $probe();
        } catch (Throwable) {
            return false;
        }
    }
}
