<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Oblige les utilisateurs web à activer la double authentification avant d'accéder à leur espace (NF-03).
 */
class EnsureTwoFactorEnrolled
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->mustEnrollTwoFactor()) {
            return redirect()->route('security.two-factor');
        }

        return $next($request);
    }
}
