<?php

namespace App\Http\Middleware;

use App\Enums\Space;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Réserve un groupe de routes à un espace : admin, company ou promoter.
 * Un utilisateur d'une compagnie ou d'un promoteur doit en plus être rattaché à cette entité.
 */
class EnsureSpace
{
    public function handle(Request $request, Closure $next, string $space): Response
    {
        $expected = Space::from($space);
        $user = $request->user();

        abort_unless($user instanceof User && $user->belongsToSpace($expected), 403);

        abort_if($expected === Space::Company && $user->company_id === null, 403);
        abort_if($expected === Space::Promoter && $user->promoter_id === null, 403);

        return $next($request);
    }
}
