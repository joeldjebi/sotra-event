<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Un compte ouvert avec un mot de passe initial donné par l'administrateur doit le remplacer
 * avant d'accéder à son espace.
 */
class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->must_change_password) {
            return redirect()->route('security.password');
        }

        return $next($request);
    }
}
