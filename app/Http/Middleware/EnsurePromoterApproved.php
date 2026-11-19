<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * L'espace promoteur n'ouvre qu'aux promoteurs validés par ACCES EVENT. Une demande en attente, refusée
 * ou suspendue mène à la page de suivi de la demande.
 */
class EnsurePromoterApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->promoter !== null && ! $user->promoter->isApproved()) {
            return redirect()->route('promoter.application');
        }

        return $next($request);
    }
}
