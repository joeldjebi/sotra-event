<?php

use App\Enums\Role;
use App\Providers\ApiDocumentationServiceProvider;
use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route as Router;

/**
 * Document OpenAPI généré une seule fois pour le fichier : la génération analyse tout le code de l'API.
 */
function openApiDocument(): array
{
    static $document = null;

    return $document ??= app(Generator::class)(Scramble::getGeneratorConfig(Scramble::DEFAULT_API));
}

/**
 * @return list<array{method: string, path: string, operation: array<string, mixed>}>
 */
function openApiOperations(array $document): array
{
    $operations = [];

    foreach ($document['paths'] as $path => $methods) {
        foreach ($methods as $method => $operation) {
            if (is_array($operation) && isset($operation['responses'])) {
                $operations[] = ['method' => strtoupper($method), 'path' => $path, 'operation' => $operation];
            }
        }
    }

    return $operations;
}

it('documente toutes les routes de l’API mobile', function () {
    $routes = collect(Router::getRoutes()->getRoutes())
        ->filter(fn (Route $route) => str_starts_with($route->uri(), 'api/v1/'))
        ->flatMap(fn (Route $route) => collect($route->methods())->reject(fn (string $m) => $m === 'HEAD')->map(fn (string $m) => $m.' /'.substr($route->uri(), strlen('api/v1/'))))
        ->sort()->values()->all();

    $documented = collect(openApiOperations(openApiDocument()))->map(fn (array $op) => $op['method'].' '.$op['path'])->sort()->values()->all();

    expect($documented)->toBe($routes);
});

it('donne à chaque opération un titre, son application, un groupe et une réponse 429', function () {
    $document = openApiDocument();
    $groups = collect($document['tags'])->pluck('name')->all();

    expect($groups)->toBe(['Authentification', 'Événements', 'Commandes', 'Pass', 'Avis et réclamations', 'Convoyeur', 'Service technique', 'Paiement', 'Webhooks', 'Supervision']);

    foreach (openApiOperations($document) as ['method' => $method, 'path' => $path, 'operation' => $operation]) {
        $where = "$method $path";

        expect($operation['summary'] ?? '')->not->toBe('', "$where : titre manquant")
            ->and($operation['description'] ?? '')->toStartWith('**', "$where : application non indiquée")
            ->and($operation['tags'] ?? [])->toHaveCount(1)
            ->and(in_array($operation['tags'][0], $groups, true))->toBeTrue("$where : groupe inconnu")
            ->and($operation['responses'])->toHaveKey('429');
    }
});

it('déclare le jeton Bearer et ne l’exige que sur les routes protégées', function () {
    $document = openApiDocument();

    expect($document['components']['securitySchemes']['sanctum'] ?? null)->toMatchArray(['type' => 'http', 'scheme' => 'bearer']);

    $protected = collect(Router::getRoutes()->getRoutes())
        ->filter(fn (Route $route) => str_starts_with($route->uri(), 'api/v1/') && in_array('auth:sanctum', $route->gatherMiddleware(), true))
        ->map(fn (Route $route) => '/'.substr($route->uri(), strlen('api/v1/')))
        ->unique()->values()->all();

    foreach (openApiOperations($document) as ['path' => $path, 'operation' => $operation]) {
        expect(($operation['security'] ?? null) === [])->toBe(! in_array($path, $protected, true), "$path : sécurité incohérente");
    }
});

it('documente les en-têtes des terminaux, les envois de photos et le guide d’intégration', function () {
    $document = openApiDocument();
    $scan = $document['paths']['/scans']['post'];
    $breakdown = $document['paths']['/breakdowns']['post']['requestBody']['content']['multipart/form-data']['schema'];

    expect(collect($scan['parameters'])->pluck('name')->all())->toContain('X-Device-Serial', 'X-App-Version')
        ->and($scan['description'])->toContain('App convoyeur')
        ->and(array_keys($breakdown['properties']))->toContain('mission_id', 'type', 'photos[0]')
        ->and($document['info']['description'])->toContain('## Contrôle hors ligne', '`accept_terms: true`', 'ALERT_ALREADY_TAKEN')
        ->and(ApiDocumentationServiceProvider::documentedAbilities())->toBe(['customer', 'conveyor', 'tech']);
});

it('décrit les champs des réponses à partir du dictionnaire', function () {
    $pass = openApiDocument()['components']['schemas']['PassResource']['properties'];

    expect($pass['qr_token']['description'])->toContain('sans réseau')
        ->and($pass['cancellation']['description'])->toContain('allowed');
});

it('ouvre le Swagger au super administrateur, avec une politique de sécurité propre aux pages de documentation', function () {
    app()->detectEnvironment(fn () => 'production');
    config(['eventtrans.security.csp' => true]);

    $this->get(route('docs.swagger'))->assertForbidden();

    $response = $this->actingAs(userWithRole(Role::SuperAdmin))->get(route('docs.swagger'))->assertOk()->assertSee('swagger-ui', false);

    expect($response->headers->get('Content-Security-Policy'))->toContain('https://unpkg.com')
        ->and($this->get('/')->headers->get('Content-Security-Policy'))->not->toContain('unpkg.com');
});

it('réserve la documentation au super administrateur hors local, sauf ouverture explicite', function () {
    app()->detectEnvironment(fn () => 'production');

    expect(Gate::forUser(userWithRole(Role::SuperAdmin))->allows('viewApiDocs'))->toBeTrue()
        ->and(Gate::forUser(userWithRole(Role::Commercial))->allows('viewApiDocs'))->toBeFalse();

    config(['eventtrans.api_docs_public' => true]);
    expect(Gate::allows('viewApiDocs'))->toBeTrue();
});

it('intègre le contenu des références dans le Swagger autonome, sans boucler sur un schéma récursif', function () {
    $document = [
        'paths' => ['/orders' => ['post' => ['requestBody' => ['content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Order']]]]]]],
        'components' => ['schemas' => [
            'Order' => ['type' => 'object', 'properties' => ['quantity' => ['type' => 'integer']]],
            'Node' => ['type' => 'object', 'properties' => ['child' => ['$ref' => '#/components/schemas/Node']]],
        ]],
    ];

    $inline = ApiDocumentationServiceProvider::inlineReferences($document, $document);

    expect($inline['paths']['/orders']['post']['requestBody']['content']['application/json']['schema'])
        ->toBe(['type' => 'object', 'properties' => ['quantity' => ['type' => 'integer']]])
        ->and($inline['components']['schemas']['Node']['properties']['child']['properties']['child'])->toBe(['$ref' => '#/components/schemas/Node']);
});
