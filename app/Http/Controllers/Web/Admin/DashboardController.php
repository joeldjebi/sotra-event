<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Console ACCES EVENT : tableau de bord (AD-07). Le contenu (filtres, indicateurs, graphiques) est le composant
 * Livewire admin.dashboard, alimenté par App\Services\Reporting\PlatformMetrics.
 */
class DashboardController extends Controller
{
    public function __invoke(): View
    {
        Gate::authorize(Permission::PlatformDashboard->value);

        return view('admin.dashboard');
    }
}
