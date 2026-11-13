<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Promoter;
use App\Models\User;
use Illuminate\View\View;

/**
 * Console ACCES EVENT : tableau de bord (vue jour J complète à l'étape 8, AD-07).
 */
class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'stats' => [
                'companies' => Company::query()->count(),
                'promoters' => Promoter::query()->count(),
                'users' => User::query()->count(),
                'customers' => Customer::query()->count(),
            ],
        ]);
    }
}
