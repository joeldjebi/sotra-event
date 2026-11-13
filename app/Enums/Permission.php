<?php

namespace App\Enums;

/**
 * Permissions des espaces web, regroupées par menu (maquettes et § 7 à 9 du CDC).
 */
enum Permission: string
{
    // Console ACCES EVENT
    case PlatformDashboard = 'platform.dashboard';
    case EventsManage = 'events.manage';
    case CompaniesManage = 'companies.manage';
    case PromotersManage = 'promoters.manage';
    case PassesManage = 'passes.manage';
    case PayoutsManage = 'payouts.manage';
    case UsersManage = 'users.manage';
    case AuditView = 'audit.view';

    // Espace compagnie : Direction commerciale
    case SalesView = 'sales.view';
    case ProposedEventsRespond = 'proposed-events.respond';
    case PricingView = 'pricing.view';
    case DemandView = 'demand.view';
    case CompanyPayoutsView = 'company-payouts.view';
    case ReportsView = 'reports.view';

    // Espace compagnie : Direction qualité
    case QualityView = 'quality.view';
    case ComplaintsManage = 'complaints.manage';

    // Espace compagnie : Exploitation
    case OperationsManage = 'operations.manage';
    case FleetManage = 'fleet.manage';
    case StaffManage = 'staff.manage';
    case BreakdownsManage = 'breakdowns.manage';
    case TechTeamsManage = 'tech-teams.manage';
    case DevicesManage = 'devices.manage';

    // Espace promoteur
    case EventTracking = 'event.tracking';
    case PromoterMessagesSend = 'promoter-messages.send';
    case PromoKitView = 'promo-kit.view';
}
