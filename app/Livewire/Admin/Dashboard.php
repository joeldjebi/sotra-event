<?php

namespace App\Livewire\Admin;

use App\Enums\AlertStatus;
use App\Enums\BreakdownUrgency;
use App\Enums\ComplaintStatus;
use App\Enums\MissionStatus;
use App\Enums\Offer;
use App\Enums\Permission;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\BreakdownAlert;
use App\Models\Company;
use App\Models\Complaint;
use App\Models\Mission;
use App\Services\Reporting\PlatformMetrics;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Console ACCES EVENT · Tableau de bord (AD-07) : ventes de la période, remplissage, pipeline des événements,
 * points d'attention. Filtres : période glissante (7, 30, 90 jours) et compagnie de transport.
 * Carte jour J (AD-07) : bus en route, pannes et réclamations en cours, toutes compagnies (ou la compagnie filtrée).
 */
class Dashboard extends Component
{
    use AuthorizesPermission;

    #[Url(as: 'periode', except: 30)]
    public int $period = 30;

    #[Url(as: 'compagnie', except: '')]
    public string $company = '';

    protected function permission(): Permission
    {
        return Permission::PlatformDashboard;
    }

    public function setPeriod(int $days): void
    {
        $this->period = in_array($days, PlatformMetrics::PERIODS, true) ? $days : 30;
    }

    /**
     * Situation jour J : missions en cours et pannes ouvertes.
     *
     * @return array{on_route: int, boarding: int, incidents: int, open_alerts: int, urgent_alerts: int, open_complaints: int, collective_complaints: int}
     */
    private function dayOf(?int $companyId): array
    {
        $missions = Mission::query()
            ->when($companyId, fn ($query) => $query->whereHas('event', fn ($event) => $event->where('company_id', $companyId)))
            ->whereIn('status', [MissionStatus::OnRoute, MissionStatus::Boarding, MissionStatus::Incident])
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $alerts = BreakdownAlert::query()
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->whereIn('status', [AlertStatus::Open, AlertStatus::Taken]);
        $complaints = Complaint::query()
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->where('status', '!=', ComplaintStatus::Resolved);

        return [
            'on_route' => (int) ($missions[MissionStatus::OnRoute->value] ?? 0),
            'boarding' => (int) ($missions[MissionStatus::Boarding->value] ?? 0),
            'incidents' => (int) ($missions[MissionStatus::Incident->value] ?? 0),
            'open_alerts' => (clone $alerts)->count(),
            'urgent_alerts' => (clone $alerts)->where('urgency', BreakdownUrgency::Urgent)->count(),
            'open_complaints' => (clone $complaints)->count(),
            'collective_complaints' => (clone $complaints)->where('is_collective', true)->count(),
        ];
    }

    public function render(): View
    {
        $period = in_array($this->period, PlatformMetrics::PERIODS, true) ? $this->period : 30;
        $companies = Company::query()->orderBy('name')->pluck('name', 'id');
        $companyId = $this->company !== '' && $companies->has((int) $this->company) ? (int) $this->company : null;

        $metrics = new PlatformMetrics($period, $companyId);
        $daily = $metrics->dailySales();
        $communes = $metrics->topCommunes();

        return view('livewire.admin.dashboard', [
            'metrics' => $metrics,
            'periods' => PlatformMetrics::PERIODS,
            'companies' => $companies->all(),
            'summary' => $metrics->summary(),
            'snapshot' => $metrics->snapshot(),
            'daily' => $daily,
            'dailyChart' => [
                'labels' => array_map(fn (array $day): string => $day['date']->translatedFormat('j M'), $daily),
                'titles' => array_map(fn (array $day): string => ucfirst($day['date']->translatedFormat('l j F')), $daily),
                'series' => [['name' => 'Pass vendus', 'values' => array_column($daily, 'passes'), 'color' => 'brand']],
                'unit' => 'Pass',
                'details' => [['name' => 'Encaissé', 'values' => array_column($daily, 'revenue'), 'format' => 'fcfa']],
            ],
            'communes' => $communes,
            'communesChart' => [
                'horizontal' => true,
                'labels' => array_column($communes, 'commune'),
                'series' => [['name' => 'Pass vendus', 'values' => array_column($communes, 'passes'), 'color' => 'brand']],
                'unit' => 'Pass',
                'details' => [['name' => 'Encaissé', 'values' => array_column($communes, 'revenue'), 'format' => 'fcfa']],
            ],
            'offers' => $metrics->salesByOffer(),
            'offerColors' => [Offer::Standard->value => 'chart-step-1', Offer::Premium->value => 'chart-step-3', Offer::Gold->value => 'chart-step-5'],
            'channels' => $metrics->salesByChannel(),
            'channelColors' => ['web' => 'chart-1', 'app' => 'chart-2', 'whatsapp' => 'chart-3'],
            'fill' => $metrics->eventFill(),
            'upcoming' => $metrics->upcomingEvents(),
            'pipeline' => $metrics->pipeline(),
            'alerts' => $metrics->alerts(),
            'recent' => $metrics->recentOrders(),
            'dayOf' => $this->dayOf($companyId),
        ]);
    }
}
