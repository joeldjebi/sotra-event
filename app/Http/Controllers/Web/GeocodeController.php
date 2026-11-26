<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Recherche d'un lieu par son nom (gare, carrefour, quartier) pour le placer sur la carte, limitée au
 * grand Abidjan. Passe par le serveur : réponses mises en cache, et la politique de sécurité du contenu
 * n'autorise pas d'appel direct du navigateur au service de géocodage (OpenStreetMap Nominatim).
 */
class GeocodeController extends Controller
{
    /** Grand Abidjan, 13 communes comprises (ouest, nord, est, sud). */
    private const VIEWBOX = '-4.35,5.60,-3.80,5.20';

    public function __invoke(Request $request): JsonResponse
    {
        $query = trim((string) $request->validate(['q' => ['required', 'string', 'min:3', 'max:120']])['q']);

        try {
            $results = Cache::remember('geocode:'.sha1(mb_strtolower($query)), now()->addWeek(), fn (): array => $this->search($query));
        } catch (ConnectionException) {
            return response()->json(['message' => 'Recherche indisponible pour le moment. Placez le point directement sur la carte.'], 503);
        }

        return response()->json(['data' => $results]);
    }

    /**
     * @return list<array{name: string, label: string, lat: float, lng: float}>
     */
    private function search(string $query): array
    {
        $response = Http::withUserAgent('ACCES TRANS (recherche de lieux d\'embarquement)')
            ->timeout(8)
            ->get((string) config('services.geocoding.url'), [
                'q' => $query,
                'format' => 'jsonv2',
                'countrycodes' => 'ci',
                'viewbox' => self::VIEWBOX,
                'bounded' => 1,
                'limit' => 6,
                'accept-language' => 'fr',
            ]);

        if ($response->failed()) {
            throw new ConnectionException('Géocodage indisponible.');
        }

        return $response->collect()
            ->filter(fn ($result) => is_array($result) && isset($result['lat'], $result['lon']))
            ->map(fn (array $result) => [
                'name' => (string) ($result['name'] ?? '') ?: strtok((string) $result['display_name'], ','),
                'label' => (string) $result['display_name'],
                'lat' => round((float) $result['lat'], 6),
                'lng' => round((float) $result['lon'], 6),
            ])
            ->values()
            ->all();
    }
}
