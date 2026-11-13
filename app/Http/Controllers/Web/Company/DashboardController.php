<?php

namespace App\Http\Controllers\Web\Company;

use App\Enums\StaffRole;
use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Espace compagnie : page d'accueil commune aux directions commerciale, qualité et exploitation.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $staffByRole = Staff::query()
            ->selectRaw('role, count(*) as total')
            ->groupBy('role')
            ->pluck('total', 'role');

        return view('company.dashboard', [
            'company' => $user->company,
            'staffCounts' => collect(StaffRole::cases())->mapWithKeys(
                fn (StaffRole $role): array => [$role->label() => (int) ($staffByRole[$role->value] ?? 0)],
            ),
        ]);
    }
}
