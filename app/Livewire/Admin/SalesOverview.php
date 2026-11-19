<?php

namespace App\Livewire\Admin;

use App\Enums\Permission;
use App\Livewire\Company\SalesReport;

/**
 * Console · Direction commerciale ACCES TRANS · Ventes par événement, toutes compagnies : même écran que
 * la direction commerciale d'une compagnie (indicateurs, offres, canaux, points, reversement, export Excel).
 * Sans compagnie rattachée, le cloisonnement ne s'applique pas : tous les événements sont proposés.
 */
class SalesOverview extends SalesReport
{
    protected function permission(): Permission
    {
        return Permission::PlatformSalesView;
    }
}
