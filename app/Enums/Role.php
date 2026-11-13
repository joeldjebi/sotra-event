<?php

namespace App\Enums;

/**
 * Rôles des utilisateurs web (§ 2 du CDC). Les profils terrain (convoyeur, technicien) sont dans StaffRole.
 */
enum Role: string
{
    case SuperAdmin = 'super_admin';
    case Operator = 'operateur';
    case Commercial = 'commercial';
    case Quality = 'qualite';
    case Operations = 'exploitation';
    case TransportLead = 'chef_projet';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super admin',
            self::Operator => 'Opérateur ACCES EVENT',
            self::Commercial => 'Direction commerciale',
            self::Quality => 'Direction qualité',
            self::Operations => 'Exploitation',
            self::TransportLead => 'Chef de projet transport',
        };
    }

    public function space(): Space
    {
        return match ($this) {
            self::SuperAdmin, self::Operator => Space::Admin,
            self::Commercial, self::Quality, self::Operations => Space::Company,
            self::TransportLead => Space::Promoter,
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
        ];

        return match ($this) {
            self::SuperAdmin => $admin,
            self::Operator => array_values(array_filter(
                $admin,
                fn (Permission $permission): bool => ! in_array($permission, [Permission::UsersManage, Permission::PayoutsManage], true),
            )),
            self::Commercial => [
                Permission::SalesView, Permission::ProposedEventsRespond, Permission::PricingView,
                Permission::DemandView, Permission::CompanyPayoutsView, Permission::ReportsView,
            ],
            self::Quality => [Permission::QualityView, Permission::ComplaintsManage],
            self::Operations => [
                Permission::OperationsManage, Permission::FleetManage, Permission::StaffManage,
                Permission::BreakdownsManage, Permission::TechTeamsManage, Permission::DevicesManage,
            ],
            self::TransportLead => [Permission::EventTracking, Permission::PromoterMessagesSend, Permission::PromoKitView],
        };
    }

    /**
     * @return list<self>
     */
    public static function forSpace(Space $space): array
    {
        return array_values(array_filter(self::cases(), fn (self $role): bool => $role->space() === $space));
    }
}
