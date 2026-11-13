<?php

namespace App\Providers;

use App\Enums\Role;
use App\Enums\TokenAbility;
use App\Models\User;
use Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\Combined\AnyOf;
use Dedoc\Scramble\Support\Generator\Header;
use Dedoc\Scramble\Support\Generator\MediaType;
use Dedoc\Scramble\Support\Generator\MissingValue;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\RequestBodyObject;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\NumberType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Documentation OpenAPI de l'API mobile (/docs/api), générée depuis le code par Scramble puis complétée :
 * guide d'intégration, schéma d'authentification Bearer, application et capacité requises route par route,
 * en-têtes des terminaux du personnel, limites (429), dictionnaire des champs (descriptions et exemples),
 * corps multipart des envois de photos, et page Swagger UI (/docs/swagger).
 */
class ApiDocumentationServiceProvider extends ServiceProvider
{
    /**
     * Limites d'appels propres à certaines routes, en plus de la limite générale de l'API.
     *
     * @var array<string, string>
     */
    private const array LIMITS = [
        'throttle:otp-send' => '3 demandes de code par numéro et par 10 minutes, 20 par heure et par adresse IP.',
        'throttle:otp-verify' => '10 vérifications par minute et par adresse IP. Chaque code accepte 5 essais.',
        'throttle:10,1' => '10 appels par minute.',
        'throttle:20,1' => '20 appels par minute.',
        'throttle:30,1' => '30 appels par minute.',
        'throttle:60,1' => '60 appels par minute.',
        'throttle:120,1' => '120 appels par minute.',
        'throttle:240,1' => '240 appels par minute.',
    ];

    /**
     * Application mobile qui utilise chaque capacité de jeton.
     *
     * @var array<string, string>
     */
    private const array APPS = [
        'customer' => 'App spectateur — jeton de capacité `customer`.',
        'conveyor' => 'App convoyeur — jeton de capacité `conveyor`, en-têtes `X-Device-Serial` et `X-App-Version`.',
        'tech' => 'App service technique — jeton de capacité `tech`, en-têtes `X-Device-Serial` et `X-App-Version`.',
    ];

    /** @var array{fields: array<string, array{0: string, 1?: mixed}>, enums: array<string, string>, parameters: array<string, string>, responses: array<string, string>}|null */
    private ?array $dictionary = null;

    public function boot(): void
    {
        // Documentation ouverte en local ; ailleurs, au super administrateur ou si API_DOCS_PUBLIC=true (recette, équipe mobile).
        Gate::define('viewApiDocs', fn (?User $user = null): bool => $this->app->isLocal()
            || (bool) config('eventtrans.api_docs_public')
            || $user?->hasRole(Role::SuperAdmin->value) === true);

        Scramble::configure()
            ->withOperationTransformers(function (Operation $operation, RouteInfo $routeInfo): void {
                $middleware = array_values(array_filter($routeInfo->route->gatherMiddleware(), 'is_string'));

                // La sécurité suit les routes réelles : seules celles protégées par Sanctum exigent un jeton.
                if (! in_array('auth:sanctum', $middleware, true)) {
                    $operation->security = [];
                }

                $this->describeAudience($operation, $middleware);

                if (in_array('staff.device', $middleware, true)) {
                    $operation->addParameters($this->deviceHeaders());
                }

                match ($routeInfo->route->getName()) {
                    'api.v1.breakdowns.store' => $operation->addRequestBodyObject($this->breakdownBody()),
                    'api.v1.tech.interventions.close' => $operation->addRequestBodyObject($this->closeBody()),
                    default => null,
                };

                $operation->addResponse($this->tooManyRequests($middleware));
            })
            ->withDocumentTransformers(function (OpenApi $openApi): void {
                $openApi->secure(
                    SecurityScheme::http('bearer', 'Sanctum')
                        ->as('sanctum')
                        ->setDescription('Jeton obtenu par `POST /auth/verify` (code SMS), à envoyer dans l’en-tête `Authorization: Bearer <jeton>`. Un jeton par appareil, sans expiration, révoqué par `POST /auth/logout`.')
                );

                $guide = resource_path('docs/api-guide.md');
                $openApi->info->description = File::exists($guide) ? File::get($guide) : $openApi->info->description;

                $this->describe($openApi);
            });

        Route::middleware(['web', RestrictedDocsAccess::class])->get('docs/swagger', fn () => view('docs.swagger', [
            'specUrl' => url('docs/api.json'),
        ]))->name('docs.swagger');
    }

    /**
     * Remplace récursivement les références internes (« #/components/... ») par leur contenu, pour le Swagger autonome :
     * sans serveur, Swagger UI ne peut pas relire le document pour suivre les « $ref ». Une référence déjà en cours
     * de résolution (schéma récursif) est laissée telle quelle.
     *
     * @param  array<array-key, mixed>  $root
     * @param  list<string>  $resolving
     */
    public static function inlineReferences(mixed $node, array $root, array $resolving = []): mixed
    {
        if (! is_array($node)) {
            return $node;
        }

        $reference = $node['$ref'] ?? null;

        if (is_string($reference) && str_starts_with($reference, '#/') && ! in_array($reference, $resolving, true)) {
            $target = $root;

            foreach (explode('/', substr($reference, 2)) as $segment) {
                $segment = str_replace(['~1', '~0'], ['/', '~'], $segment);

                if (! is_array($target) || ! array_key_exists($segment, $target)) {
                    return $node;
                }

                $target = $target[$segment];
            }

            $siblings = $node;
            unset($siblings['$ref']);
            $resolved = self::inlineReferences($target, $root, [...$resolving, $reference]);

            return is_array($resolved) ? [...$resolved, ...$siblings] : $resolved;
        }

        foreach ($node as $key => $value) {
            $node[$key] = self::inlineReferences($value, $root, $resolving);
        }

        return $node;
    }

    /**
     * Indique en tête de chaque appel l'application qui l'utilise (d'après la capacité exigée du jeton).
     *
     * @param  list<string>  $middleware
     */
    private function describeAudience(Operation $operation, array $middleware): void
    {
        $abilities = collect($middleware)
            ->filter(fn (string $name): bool => str_starts_with($name, 'abilities:') || str_starts_with($name, 'ability:'))
            ->flatMap(fn (string $name): array => explode(',', substr($name, strpos($name, ':') + 1)))
            ->all();

        $audience = match (true) {
            $abilities !== [] => implode(' ', array_map(fn (string $ability): string => self::APPS[$ability] ?? "Jeton de capacité `{$ability}`.", $abilities)),
            in_array('auth:sanctum', $middleware, true) => 'Toutes les applications — tout jeton.',
            default => 'Public — sans jeton.',
        };

        $operation->description(trim('**'.$audience.'**'."\n\n".$operation->description));
    }

    /**
     * En-têtes des terminaux du personnel (apps convoyeur et technique).
     *
     * @return list<Parameter>
     */
    private function deviceHeaders(): array
    {
        return [
            Parameter::make('X-Device-Serial', 'header')
                ->description('Numéro de série du PDA. Un terminal enregistré puis bloqué reçoit **403** `DEVICE_BLOCKED`. Facultatif pour un smartphone personnel.')
                ->setSchema(Schema::fromType((new StringType)->example('TC21-0042'))),
            Parameter::make('X-App-Version', 'header')
                ->description('Version de l’application installée, affichée à l’exploitation.')
                ->setSchema(Schema::fromType((new StringType)->example('1.4.0'))),
        ];
    }

    /**
     * Corps multipart de POST /breakdowns, à plat : l'outil d'essai téléverse un fichier par champ (« photos[0] »…),
     * et PHP les regroupe dans la liste « photos ».
     */
    private function breakdownBody(): RequestBodyObject
    {
        $body = (new ObjectType)
            ->addProperty('mission_id', (new IntegerType)->setDescription('Mission en cours (`GET /me/missions`).')->example(130))
            ->addProperty('type', (new StringType)->enum(['moteur', 'pneu', 'freins', 'electrique', 'accident', 'autre'])->setDescription('Type de panne. `accident` rend l’alerte urgente d’office.')->example('pneu'))
            ->addProperty('details', (new StringType)->setDescription('Précisions, 1 000 caractères maximum.')->example('Crevaison pneu avant droit'))
            ->addProperty('latitude', (new NumberType)->setDescription('Latitude de la panne.')->example(5.3101))
            ->addProperty('longitude', (new NumberType)->setDescription('Longitude de la panne.')->example(-3.995))
            ->addProperty('accuracy_m', (new IntegerType)->setDescription('Précision GPS en mètres.')->example(8));

        $this->addPhotos($body, 4, 'Photo de la panne');
        $body->setRequired(['mission_id', 'type']);

        return RequestBodyObject::make()
            ->setContent('multipart/form-data', Schema::fromType($body))
            ->description('`multipart/form-data` si des photos sont jointes, sinon JSON avec les mêmes champs.')
            ->required();
    }

    /**
     * Corps multipart de POST /tech/interventions/{intervention}/close.
     */
    private function closeBody(): RequestBodyObject
    {
        $text = fn (string $description, string $example): Type => (new StringType)->setDescription($description)->example($example);

        $body = (new ObjectType)
            ->addProperty('result', (new StringType)->enum(['repare', 'remplace', 'remorquage'])->setDescription('Issue : `repare` (le bus repart), `remplace` (bus de réserve) ou `remorquage`.')->example('repare'))
            ->addProperty('replacement_bus_id', (new IntegerType)->setDescription('Bus de réserve, **requis si `remplace`** (`GET /tech/reserve-buses?alert=…`). Les Pass passent sur ce bus et les passagers sont prévenus.')->example(21))
            ->addProperty('diagnosis', $text('Diagnostic, 2 000 caractères maximum.', 'Roue changée'))
            ->addProperty('parts', $text('Pièces utilisées.', 'Roue de secours'))
            ->addProperty('duration_min', (new IntegerType)->setDescription('Durée en minutes.')->example(15))
            ->addProperty('towing_requested', (new BooleanType)->setDescription('Remorquage demandé.')->example(false))
            ->addProperty('notes', $text('Remarques.', 'Bus à contrôler au dépôt'));

        $this->addPhotos($body, 6, 'Photo de l’intervention');
        $body->setRequired(['result']);

        return RequestBodyObject::make()
            ->setContent('multipart/form-data', Schema::fromType($body))
            ->description('`multipart/form-data` si des photos sont jointes, sinon JSON avec les mêmes champs.')
            ->required();
    }

    private function addPhotos(ObjectType $body, int $max, string $label): void
    {
        $file = (new StringType)->format('binary')->contentMediaType('application/octet-stream');

        foreach (range(0, min($max, 3) - 1) as $index) {
            $body->addProperty("photos[{$index}]", (clone $file)->setDescription($index === 0
                ? "{$label} (facultatif) : image, 6 Mo maximum. Jusqu’à {$max} photos (`photos[]`)."
                : 'Photo '.($index + 1).' (facultative).'));
        }
    }

    /**
     * Applique le dictionnaire des champs (resources/docs/api-fields.php) aux schémas nommés, aux réponses et aux
     * corps de requête en ligne, aux paramètres, et traduit les descriptions par défaut de Scramble.
     */
    private function describe(OpenApi $openApi): void
    {
        $dictionary = $this->dictionary();

        foreach ($openApi->components->schemas as $name => $schema) {
            if (! $schema instanceof Schema) {
                continue;
            }

            $short = class_basename((string) $name);

            if (isset($dictionary['enums'][$short]) && $schema->type->description === '') {
                $schema->type->setDescription($dictionary['enums'][$short]);
            }

            $this->describeType($schema->type, $short, '');
        }

        foreach ($openApi->components->responses as $response) {
            if ($response instanceof Response) {
                $response->setDescription($dictionary['responses'][$response->description] ?? $response->description);
            }
        }

        foreach ($openApi->paths as $path) {
            foreach ($path->operations as $operation) {
                foreach ($operation->parameters as $parameter) {
                    if ($parameter instanceof Parameter && isset($dictionary['parameters'][$parameter->name]) && $parameter->description === '') {
                        $parameter->description = $dictionary['parameters'][$parameter->name];
                    }
                }

                $body = $operation->requestBodyObject;

                if ($body instanceof RequestBodyObject) {
                    foreach ($body->content as $content) {
                        $schema = $content instanceof MediaType ? $content->schema : $content;

                        if ($schema instanceof Schema) {
                            $this->describeType($schema->type, '', '');
                        }
                    }
                }

                foreach ($operation->responses ?? [] as $response) {
                    if (! $response instanceof Response) {
                        continue;
                    }

                    $response->setDescription($this->responseDescription($response));

                    foreach ($response->content as $content) {
                        $schema = $content instanceof MediaType ? $content->schema : $content;

                        if ($schema instanceof Schema) {
                            $this->describeType($schema->type, '', '');
                        }
                    }
                }
            }
        }
    }

    private function responseDescription(Response $response): string
    {
        $description = $this->dictionary()['responses'][$response->description] ?? $response->description;

        // Scramble décrit les succès par le nom technique de la ressource (« `PassResource` ») : on le remplace.
        if ($description === '' || preg_match('/^(Array of |Paginated set of )?`\w+`(\n\n`\w+`)*$/', $description) === 1) {
            return match ((int) $response->code) {
                201 => 'Créé.',
                202 => 'Demande acceptée.',
                204 => 'Succès, sans contenu.',
                default => 'Succès.',
            };
        }

        return $description;
    }

    private function describeType(Type $type, string $schemaName, string $path): void
    {
        if ($type instanceof ObjectType) {
            foreach ($type->properties as $name => $property) {
                if (! $property instanceof Type) {
                    continue;
                }

                $propertyPath = $path === '' ? (string) $name : $path.'.'.$name;
                $this->applyEntry($property, $schemaName, $propertyPath, (string) $name);
                $this->describeType($property, $schemaName, $propertyPath);
            }
        }

        if ($type instanceof ArrayType) {
            $this->describeType($type->items, $schemaName, $path.'[]');
        }

        if ($type instanceof AnyOf) {
            foreach ($type->items as $item) {
                $this->describeType($item, $schemaName, $path);
            }
        }
    }

    private function applyEntry(Type $type, string $schemaName, string $path, string $name): void
    {
        $fields = $this->dictionary()['fields'];
        // Du plus précis au plus général : « Schéma.chemin », puis chaque suffixe du chemin (« data.alert.bus » →
        // « alert.bus » → « bus »… sans les « [] » des listes), puis « *.nom ».
        $candidates = $schemaName !== '' ? [$schemaName.'.'.$path] : [];
        $segments = explode('.', str_replace('[]', '', $path));

        foreach (array_keys($segments) as $start) {
            $candidates[] = implode('.', array_slice($segments, $start));
        }

        $candidates[] = '*.'.$name;
        $entry = null;

        foreach ($candidates as $candidate) {
            if (isset($fields[$candidate])) {
                $entry = $fields[$candidate];

                break;
            }
        }

        if ($entry === null) {
            return;
        }

        if ($type->description === '') {
            $type->setDescription($entry[0]);
        }

        if (array_key_exists(1, $entry) && $type->example instanceof MissingValue) {
            $type->example($entry[1]);
        }
    }

    /**
     * @param  list<string>  $middleware
     */
    private function tooManyRequests(array $middleware): Response
    {
        $specific = collect($middleware)->map(fn (string $name): ?string => self::LIMITS[$name] ?? null)->filter()->implode(' ');
        $general = 'Limite générale : 300 appels par minute et par compte (600 par adresse IP sans connexion).';

        $body = (new ObjectType)
            ->addProperty('message', (new StringType)->example('Trop de tentatives. Veuillez réessayer dans 60 secondes.'))
            ->setRequired(['message']);

        $response = Response::make(429)
            ->setDescription(implode(' ', array_filter(['Trop de requêtes.', $specific, $general, 'Attendre la durée indiquée par l’en-tête `Retry-After` avant de réessayer.'])))
            ->setContent('application/json', Schema::fromType($body));

        $response->addHeader('Retry-After', new Header(
            description: 'Nombre de secondes à attendre avant de réessayer.',
            schema: Schema::fromType((new IntegerType)->example(60)),
        ));

        return $response;
    }

    /**
     * @return array{fields: array<string, array{0: string, 1?: mixed}>, enums: array<string, string>, parameters: array<string, string>, responses: array<string, string>}
     */
    private function dictionary(): array
    {
        /** @var array{fields: array<string, array{0: string, 1?: mixed}>, enums: array<string, string>, parameters: array<string, string>, responses: array<string, string>} $dictionary */
        $dictionary = $this->dictionary ??= require resource_path('docs/api-fields.php');

        return $dictionary;
    }

    /**
     * Valeurs des capacités, pour la cohérence avec l'énumération (utilisée par les tests).
     *
     * @return list<string>
     */
    public static function documentedAbilities(): array
    {
        return array_values(array_intersect(array_keys(self::APPS), array_map(fn (TokenAbility $ability): string => $ability->value, TokenAbility::cases())));
    }
}
