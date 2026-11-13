<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes de sécurité (NF-03) sur toutes les réponses web et API :
 * - anti-détournement de clic, anti-reniflage MIME, politique de référent et de permissions ;
 * - HSTS dès que la requête est en HTTPS ;
 * - politique de sécurité du contenu (CSP) quand eventtrans.security.csp est actif (production) :
 *   Alpine (Livewire) exige 'unsafe-eval', le script de thème est en ligne, les tuiles viennent d'OpenStreetMap.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), payment=(), geolocation=(self)');
        $headers->remove('X-Powered-By');

        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if (config('eventtrans.security.csp')) {
            $headers->set('Content-Security-Policy', self::contentSecurityPolicy());
        }

        return $response;
    }

    public static function contentSecurityPolicy(): string
    {
        $connect = ["'self'"];
        $host = config('broadcasting.connections.reverb.options.host');

        if (is_string($host) && $host !== '') {
            $scheme = config('broadcasting.connections.reverb.options.scheme') === 'https' ? 'wss' : 'ws';
            $connect[] = "{$scheme}://{$host}:".config('broadcasting.connections.reverb.options.port');
        }

        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob: https://*.tile.openstreetmap.org",
            "font-src 'self' data:",
            'connect-src '.implode(' ', $connect),
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
        ]);
    }
}
