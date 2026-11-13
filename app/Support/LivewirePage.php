<?php

namespace App\Support;

use App\Enums\Permission;
use Illuminate\Routing\Route as RouteInstance;
use Illuminate\Support\Facades\Route;

/**
 * Déclare une page d'espace web portée par un composant Livewire et protégée par une permission.
 */
final class LivewirePage
{
    public static function route(string $uri, string $name, string $component, string $title, Permission $permission): RouteInstance
    {
        return Route::view($uri, 'pages.livewire', ['livewireComponent' => $component, 'title' => $title])
            ->middleware('can:'.$permission->value)
            ->name($name);
    }
}
