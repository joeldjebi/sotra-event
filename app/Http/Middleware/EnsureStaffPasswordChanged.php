<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Models\Staff;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Le personnel doit remplacer le mot de passe remis par l'exploitation avant d'utiliser l'application.
 */
class EnsureStaffPasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $staff = $request->user('sanctum');

        if ($staff instanceof Staff && $staff->must_change_password) {
            throw new ApiException('Choisissez un nouveau mot de passe pour continuer.', 'PASSWORD_CHANGE_REQUIRED', 403);
        }

        return $next($request);
    }
}
