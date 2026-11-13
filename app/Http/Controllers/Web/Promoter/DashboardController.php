<?php

namespace App\Http\Controllers\Web\Promoter;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Espace promoteur : suivi de l'événement (temps réel à l'étape 8, PR-01 à PR-03).
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        return view('promoter.dashboard', ['promoter' => $user->promoter]);
    }
}
