<?php

use App\Http\Controllers\Api\V1\HealthController;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('orders:expire')->everyMinute()->withoutOverlapping();
Schedule::command('payments:reconcile')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('passes:remind')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('missions:check-delays')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('payouts:generate')->dailyAt('03:00')->withoutOverlapping();
Schedule::command('model:prune')->dailyAt('02:30')->withoutOverlapping();

// Battement du planificateur, contrôlé par /api/v1/health (NF-08).
Schedule::call(fn () => Cache::forever(HealthController::SCHEDULER_HEARTBEAT, now()->timestamp))->everyMinute()->name('health-heartbeat');
