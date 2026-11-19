<?php

namespace App\Livewire\Admin;

use App\Enums\AlertStatus;
use App\Enums\EventStatus;
use App\Enums\Permission;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\BreakdownAlert;
use App\Models\Event;
use App\Services\Reporting\PromoterEventInsights;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Console · Direction technique ACCES TRANS · Supervision en direct, toutes compagnies : pannes ouvertes
 * de tous les événements, puis pour l'événement choisi la carte des bus, les départs (ponctualité) et les pannes.
 * Consultation seule, sans montant : les décisions restent à l'exploitation de chaque compagnie.
 */
class OperationsMonitor extends Component
{
    use AuthorizesPermission;

    public const TABS = ['carte' => 'Carte en direct', 'transport' => 'Départs et ponctualité', 'incidents' => 'Pannes'];

    #[Url(as: 'evenement')]
    public ?int $eventId = null;

    #[Url(as: 'onglet', except: 'carte')]
    public string $tab = 'carte';

    protected function permission(): Permission
    {
        return Permission::OperationsSupervise;
    }

    public function mount(): void
    {
        // Par défaut : l'événement en vente le plus proche (le jour J d'abord), sinon le dernier terminé.
        $this->eventId ??= $this->events()->where('status', EventStatus::OnSale)->where('ends_at', '>=', now()->subHours(6))->orderBy('starts_at')->value('id')
            ?? $this->events()->orderByDesc('starts_at')->value('id');
    }

    /**
     * @return Builder<Event>
     */
    private function events(): Builder
    {
        return Event::query()->whereIn('status', [EventStatus::OnSale, EventStatus::Finished]);
    }

    public function render(PromoterEventInsights $insights): View
    {
        $event = $this->eventId ? $this->events()->with(['venue', 'company'])->find($this->eventId) : null;
        $tab = array_key_exists($this->tab, self::TABS) ? $this->tab : 'carte';

        return view('livewire.admin.operations-monitor', [
            'event' => $event,
            'tabs' => self::TABS,
            'currentTab' => $tab,
            'eventOptions' => $this->events()->with('company')->orderByDesc('starts_at')->limit(50)->get()
                ->mapWithKeys(fn (Event $item): array => [$item->id => $item->name.' · '.$item->company->name.' · '.$item->starts_at->format('d/m')])->all(),
            'openAlerts' => BreakdownAlert::query()
                ->with(['event', 'bus', 'mission.boardingPoint', 'intervention'])
                ->where('status', '!=', AlertStatus::Closed)
                ->latest()
                ->limit(20)
                ->get(),
            'live' => $event && $tab === 'carte' ? $insights->liveMap($event) : null,
            'transport' => $event && $tab === 'transport' ? $insights->transport($event) : null,
            'incidents' => $event && $tab === 'incidents' ? $insights->incidents($event) : null,
        ]);
    }
}
