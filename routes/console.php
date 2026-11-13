<?php

use App\Http\Controllers\Api\V1\HealthController;
use App\Providers\ApiDocumentationServiceProvider;
use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
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

/*
 * Documentation de l'API mobile à partager hors de l'application : fichier OpenAPI et page Swagger UI autonome,
 * qui s'ouvre d'un double clic sans serveur. À relancer après chaque changement de l'API.
 */
Artisan::command('api:docs', function () {
    $document = app(Generator::class)(Scramble::getGeneratorConfig(Scramble::DEFAULT_API));
    $document['servers'] = [['url' => rtrim((string) config('app.url'), '/').'/api/v1', 'description' => 'Serveur configuré (APP_URL)']];
    $json = (string) json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    File::ensureDirectoryExists(base_path('docs/api'));
    File::put(base_path('docs/api/openapi.json'), $json."\n");
    // Sans serveur, Swagger UI ne peut pas suivre les références « $ref » : on intègre une copie où chacune est remplacée par son contenu.
    $inline = (string) json_encode(ApiDocumentationServiceProvider::inlineReferences($document, $document), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    File::put(base_path('docs/api/swagger.html'), view('docs.swagger-standalone', ['spec' => $inline])->render());

    $operations = 0;

    foreach (is_array($document['paths'] ?? null) ? $document['paths'] : [] as $methods) {
        foreach (is_array($methods) ? $methods : [] as $operation) {
            $operations += is_array($operation) && isset($operation['responses']) ? 1 : 0;
        }
    }

    $this->components->info("Documentation exportée : {$operations} opérations → docs/api/openapi.json et docs/api/swagger.html");
})->purpose('Exporte la documentation de l’API mobile (OpenAPI JSON et page Swagger UI autonome)');
