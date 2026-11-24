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
                    ['Événements', 'calendar', 'admin.events', Permission::EventsView],
                    ['Lieux', 'map-pin', 'admin.venues', Permission::EventsManage],
                    ['Compagnies', 'building', 'admin.companies', Permission::CompaniesManage],
                    ['Promoteurs', 'megaphone', 'admin.promoters', Permission::PromotersView],
                    ['Demandes des promoteurs', 'inbox', 'admin.event-requests', Permission::EventsView],
                    ['Pass & ventes', 'ticket', 'admin.passes', Permission::PassesManage],
                    ['Remboursements', 'lifebuoy', 'admin.refunds', Permission::PassesManage],
                    ['Reversements', 'banknotes', 'admin.payouts', Permission::PayoutsView],
                    ['Utilisateurs & rôles', 'users', 'admin.users', Permission::UsersManage],
                    ['Utilisateurs mobiles', 'device', 'admin.mobile-users', Permission::MobileUsersView],
                    ['Journal d\'audit', 'shield', 'admin.audit', Permission::AuditView],
                ]],
                ['Direction commerciale', [
                    ['Ventes par événement', 'chart', 'admin.sales', Permission::PlatformSalesView],
                ]],
                ['Direction technique', [
                    ['Supervision en direct', 'map', 'admin.operations', Permission::OperationsSupervise],
                    ['Terminaux', 'device', 'admin.devices', Permission::OperationsSupervise],
                ]],
                ['Centre d\'appel', [
                    ['Fiche spectateur', 'phone', 'admin.call-center', Permission::CallCenterAccess],
                ]],
            ],
            Space::Company => [
                [null, [['Accueil', 'home', 'company.dashboard', null]]],
                ['Direction commerciale', [
                    ['Ventes', 'chart', 'company.sales', Permission::SalesView],
                    ['Événements proposés', 'calendar', 'company.proposed-events', Permission::ProposedEventsView],
                    ['Offres & grille tarifaire', 'tag', 'company.pricing', Permission::PricingView],
                    ['Demande par commune', 'map-pin', 'company.demand', Permission::DemandView],
                    ['Reversements', 'banknotes', 'company.payouts', Permission::CompanyPayoutsView],
                    ['Rapports', 'document', 'company.reports', Permission::ReportsView],
                ]],
                ['Qualité de service', [
                    ['Qualité de service', 'star', 'company.quality.overview', Permission::QualityView],
                    ['Avis des spectateurs', 'chat', 'company.quality.reviews', Permission::QualityView],
                    ['Réclamations', 'lifebuoy', 'company.quality.complaints', Permission::QualityView],
                    ['Ponctualité', 'clock', 'company.quality.punctuality', Permission::QualityView],
                    ['Équipages', 'users', 'company.quality.crews', Permission::QualityView],
                    ['Bilans du pilote', 'document', 'company.quality.pilot', Permission::QualityView],
                ]],
                ['Direction de l\'exploitation', [
                    ['Exploitation en direct', 'map', 'company.live', Permission::OperationsView],
                    ['Plan de transport', 'route', 'company.transport-plan', Permission::OperationsView],
                    ['Bus & affectations', 'bus', 'company.assignments', Permission::FleetView],
                    ['Dépôts', 'building', 'company.depots', Permission::FleetView],
                    ['Chauffeurs & convoyeurs', 'id-card', 'company.staff', Permission::StaffView],
                    ['Pannes & interventions', 'wrench', 'company.breakdowns', Permission::BreakdownsView],
                    ['Équipes service technique', 'users', 'company.tech-teams', Permission::TechTeamsView],
                    ['PDA & terminaux', 'device', 'company.devices', Permission::DevicesView],
                ]],
                ['Administration', [
                    ['Équipe SOTRA', 'users', 'company.team', Permission::CompanyTeamManage],
                ]],
            ],
            Space::Promoter => [
                [null, [
                    ['Suivi de l\'événement', 'home', 'promoter.dashboard', Permission::EventTracking],
                    ['Mes événements', 'ticket', 'promoter.events', Permission::EventTracking],
                    ['Fiche événement', 'calendar', 'promoter.event', Permission::EventTracking],
                    ['Zones de dépose', 'map-pin', 'promoter.drop-zones', Permission::EventTracking],
                    ['Messages aux spectateurs', 'chat', 'promoter.messages', Permission::EventTracking],
                    ['Kit de promotion', 'qr', 'promoter.kit', Permission::PromoKitView],
                    ['Bilan', 'document', 'promoter.report', Permission::EventTracking],
                ]],
                ['Ma structure', [
                    ['Demandes d\'événement', 'sparkles', 'promoter.event-requests', Permission::EventRequestsSubmit],
                    ['Mon équipe', 'users', 'promoter.team', Permission::PromoterTeamManage],
                    ['Dossier', 'id-card', 'promoter.organisation', Permission::PromoterTeamManage],
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

                $exists = Route::has($route);

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
