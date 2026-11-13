<?php

namespace App\Support;

use App\Enums\Permission;
use App\Enums\Role;
use App\Enums\Space;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Menus latéraux des espaces web, repris des maquettes (§ 7 à 9 du CDC).
 *
 * Un élément sans route existante est affiché « Bientôt » : il sera branché à l'étape correspondante.
 */
final class Navigation
{
    /**
     * @return list<array{title: string|null, items: list<array{label: string, icon: string, url: string|null, active: bool}>}>
     */
    public static function for(User $user): array
    {
        $sections = match ($user->space()) {
            Space::Admin => [
                [null, [
                    ['Tableau de bord', 'home', 'admin.dashboard', Permission::PlatformDashboard],
                    ['Événements', 'calendar', 'admin.events', Permission::EventsManage],
                    ['Lieux', 'map-pin', 'admin.venues', Permission::EventsManage],
                    ['Compagnies', 'building', 'admin.companies', Permission::CompaniesManage],
                    ['Promoteurs', 'megaphone', 'admin.promoters', Permission::PromotersManage],
                    ['Pass & ventes', 'ticket', null, Permission::PassesManage],
                    ['Reversements', 'banknotes', null, Permission::PayoutsManage],
                    ['Utilisateurs & rôles', 'users', 'admin.users', Permission::UsersManage],
                    ['Journal d\'audit', 'shield', 'admin.audit', Permission::AuditView],
                ]],
            ],
            Space::Company => [
                [null, [['Accueil', 'home', 'company.dashboard', null]]],
                ['Direction commerciale', [
                    ['Ventes', 'chart', 'company.sales', Permission::SalesView],
                    ['Événements proposés', 'calendar', 'company.proposed-events', Permission::ProposedEventsRespond],
                    ['Offres & grille tarifaire', 'tag', null, Permission::PricingView],
                    ['Demande par commune', 'map-pin', null, Permission::DemandView],
                    ['Reversements', 'banknotes', null, Permission::CompanyPayoutsView],
                    ['Rapports', 'document', null, Permission::ReportsView],
                ]],
                ['Direction qualité', [
                    ['Qualité de service', 'star', null, Permission::QualityView],
                    ['Avis des spectateurs', 'chat', null, Permission::QualityView],
                    ['Réclamations', 'lifebuoy', null, Permission::ComplaintsManage],
                    ['Ponctualité', 'clock', null, Permission::QualityView],
                    ['Équipages', 'users', null, Permission::QualityView],
                    ['Bilans du pilote', 'document', null, Permission::QualityView],
                ]],
                ['Exploitation', [
                    ['Exploitation en direct', 'map', 'company.live', Permission::OperationsManage],
                    ['Plan de transport', 'route', 'company.transport-plan', Permission::OperationsManage],
                    ['Bus & affectations', 'bus', 'company.assignments', Permission::FleetManage],
                    ['Dépôts', 'building', 'company.depots', Permission::FleetManage],
                    ['Chauffeurs & convoyeurs', 'id-card', 'company.staff', Permission::StaffManage],
                    ['Pannes & interventions', 'wrench', 'company.breakdowns', Permission::BreakdownsManage],
                    ['Équipes service technique', 'users', 'company.tech-teams', Permission::TechTeamsManage],
                    ['PDA & terminaux', 'device', 'company.devices', Permission::DevicesManage],
                ]],
            ],
            Space::Promoter => [
                [null, [
                    ['Suivi de l\'événement', 'home', 'promoter.dashboard', Permission::EventTracking],
                    ['Fiche événement', 'calendar', null, Permission::EventTracking],
                    ['Zones de dépose', 'map-pin', null, Permission::EventTracking],
                    ['Messages aux spectateurs', 'chat', null, Permission::PromoterMessagesSend],
                    ['Kit de promotion', 'qr', 'promoter.kit', Permission::PromoKitView],
                    ['Bilan', 'document', null, Permission::EventTracking],
                ]],
            ],
            null => [],
        };

        $menu = [];

        foreach ($sections as [$title, $items]) {
            $visible = [];

            foreach ($items as [$label, $icon, $route, $permission]) {
                if ($permission instanceof Permission && ! $user->can($permission->value)) {
                    continue;
                }

                $exists = $route !== null && Route::has($route);

                $visible[] = [
                    'label' => $label,
                    'icon' => $icon,
                    'url' => $exists ? route($route) : null,
                    'active' => $exists && (request()->routeIs($route) || request()->routeIs($route.'.*')),
                ];
            }

            if ($visible !== []) {
                $menu[] = ['title' => $title, 'items' => $visible];
            }
        }

        return $menu;
    }

    /**
     * Libellé des rôles de l'utilisateur, pour le bloc profil (ex. « Direction commerciale »).
     */
    public static function roleLabel(User $user): string
    {
        return implode(' · ', array_map(fn (Role $role): string => $role->label(), $user->businessRoles()));
    }
}
