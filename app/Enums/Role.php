<?php

namespace App\Enums;

/**
 * Rôles des utilisateurs web (§ 2 du CDC). Les profils terrain (convoyeur, technicien) sont dans StaffRole.
 */
enum Role: string
{
    case SuperAdmin = 'super_admin';
    case Operator = 'operateur';
    case PlatformCommercial = 'direction_commerciale';
    case PlatformTechnical = 'direction_technique';
    case CallCenter = 'centre_appel';
    case CompanyAdmin = 'admin_compagnie';
    case GeneralManagement = 'direction_generale';
    case Commercial = 'commercial';
    case Quality = 'qualite';
    case Operations = 'exploitation';
    case TransportLead = 'chef_projet';
    case PromoterAdmin = 'admin_promoteur';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super admin',
            self::Operator => 'Opérateur ACCES EVENT',
            self::PlatformCommercial => 'Direction commerciale ACCES TRANS',
            self::PlatformTechnical => 'Direction technique ACCES TRANS',
            self::CallCenter => 'Centre d\'appel',
            self::CompanyAdmin => 'Administrateur SOTRA',
            self::GeneralManagement => 'Direction générale SOTRA',
            self::Commercial => 'Direction commerciale SOTRA',
            self::Quality => 'Direction qualité',
            self::Operations => 'Direction de l\'exploitation SOTRA',
            self::TransportLead => 'Chef de projet transport',
            self::PromoterAdmin => 'Administrateur promoteur',
        };
    }

    public function space(): Space
    {
        return match ($this) {
            self::SuperAdmin, self::Operator, self::PlatformCommercial, self::PlatformTechnical, self::CallCenter => Space::Admin,
            self::CompanyAdmin, self::GeneralManagement, self::Commercial, self::Quality, self::Operations => Space::Company,
            self::TransportLead, self::PromoterAdmin => Space::Promoter,
        };
    }

    /**
     * @return list<Permission>
     */
    public function permissions(): array
    {
        $admin = [
            Permission::PlatformDashboard, Permission::EventsManage, Permission::CompaniesManage,
            Permission::PromotersManage, Permission::PassesManage, Permission::PayoutsManage,
            Permission::UsersManage, Permission::AuditView,
            Permission::EventsView, Permission::PromotersView, Permission::PayoutsView, Permission::PlatformSalesView,
            Permission::OperationsSupervise, Permission::CallCenterAccess,
        ];

        $commercial = [
            Permission::SalesView, Permission::ProposedEventsView, Permission::ProposedEventsRespond, Permission::PricingView,
            Permission::DemandView, Permission::CompanyPayoutsView, Permission::ReportsView,
        ];

        // Direction de l'exploitation : les bus et le transport des passagers, qualité de service comprise.
        $operations = [
            Permission::OperationsView, Permission::OperationsManage, Permission::FleetView, Permission::FleetManage,
            Permission::StaffView, Permission::StaffManage, Permission::BreakdownsView, Permission::BreakdownsManage,
            Permission::TechTeamsView, Permission::TechTeamsManage, Permission::DevicesView, Permission::DevicesManage,
            Permission::QualityView, Permission::ComplaintsManage,
        ];

        // Direction générale : tout l'espace compagnie, en consultation seule.
        $generalManagement = [
            Permission::SalesView, Permission::ProposedEventsView, Permission::PricingView, Permission::DemandView,
            Permission::CompanyPayoutsView, Permission::ReportsView, Permission::QualityView, Permission::OperationsView,
            Permission::FleetView, Permission::StaffView, Permission::BreakdownsView, Permission::TechTeamsView,
            Permission::DevicesView,
        ];

        return match ($this) {
            self::SuperAdmin => $admin,
            self::Operator => array_values(array_filter(
                $admin,
                fn (Permission $permission): bool => ! in_array($permission, [Permission::UsersManage, Permission::PayoutsManage, Permission::PayoutsView], true),
            )),
            // Équipes ACCES TRANS : consultation, sans les réglages ni les comptes.
            self::PlatformCommercial => [
                Permission::PlatformDashboard, Permission::PlatformSalesView, Permission::EventsView,
                Permission::PromotersView, Permission::PayoutsView,
            ],
            self::PlatformTechnical => [Permission::OperationsSupervise],
            self::CallCenter => [Permission::CallCenterAccess],
            // L'administrateur SOTRA : tout l'espace compagnie, plus les comptes de son équipe.
            self::CompanyAdmin => [...$commercial, ...$operations, Permission::CompanyTeamManage],
            self::GeneralManagement => $generalManagement,
            self::Commercial => $commercial,
            self::Quality => [Permission::QualityView, Permission::ComplaintsManage],
            self::Operations => $operations,
            self::TransportLead => [Permission::EventTracking, Permission::PromoterMessagesSend, Permission::PromoKitView],
            // L'administrateur du promoteur : mêmes écrans, plus son équipe et ses demandes d'événement.
            self::PromoterAdmin => [
                Permission::EventTracking, Permission::PromoterMessagesSend, Permission::PromoKitView,
                Permission::PromoterTeamManage, Permission::EventRequestsSubmit,
            ],
        };
    }

    /**
     * Rôles que l'administrateur SOTRA attribue à son équipe (la direction qualité est reprise par l'exploitation).
     *
     * @return list<self>
     */
    public static function companyTeam(): array
    {
        return [self::CompanyAdmin, self::GeneralManagement, self::Commercial, self::Operations];
    }

    /**
     * @return list<self>
     */
    public static function forSpace(Space $space): array
    {
        return array_values(array_filter(self::cases(), fn (self $role): bool => $role->space() === $space));
    }
}
