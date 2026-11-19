<?php

namespace App\Livewire\Promoter;

use App\Enums\Permission;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\Event;
use App\Services\Reporting\PromoterEventInsights;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Espace promoteur · Détail d'un événement, en onglets : Résumé, Ventes, Points d'embarquement, Embarquement,
 * Transport, Incidents, Avis, Réclamations, Messages et Annulations.
 * Volumes seulement : ni montants, ni données nominatives des spectateurs.
 */
class EventDetail extends Component
{
    use AuthorizesPermission;

    public const TABS = [
        'resume' => 'Résumé',
        'ventes' => 'Ventes',
        'points' => 'Points d\'embarquement',
        'embarquement' => 'Embarquement',
        'transport' => 'Transport',
        'incidents' => 'Incidents',
        'avis' => 'Avis',
        'reclamations' => 'Réclamations',
        'messages' => 'Messages',
        'annulations' => 'Annulations',
    ];

    #[Locked]
    public int $eventId;

    #[Url(as: 'onglet', except: 'resume')]
    public string $tab = 'resume';

    protected function permission(): Permission
    {
        return Permission::EventTracking;
    }

    public function mount(int $eventId): void
    {
        $this->eventId = $eventId;
        $this->event();

        if (! array_key_exists($this->tab, self::TABS)) {
            $this->tab = 'resume';
        }
    }

    /**
     * L'événement du promoteur connecté (scope promoteur), à partir de son acceptation.
     */
    private function event(): Event
    {
        return Event::query()
            ->whereIn('status', PromoterEventInsights::visibleStatuses())
            ->with(['venue', 'company', 'offers', 'dropZones', 'boardingPoints' => fn ($query) => $query->orderBy('position')])
            ->findOrFail($this->eventId);
    }

    public function render(PromoterEventInsights $insights): View
    {
        $event = $this->event();
        $tab = array_key_exists($this->tab, self::TABS) ? $this->tab : 'resume';

        return view('livewire.promoter.event-detail', [
            'event' => $event,
            'tabs' => self::TABS,
            'summary' => $insights->summary($event),
            'sales' => $tab === 'ventes' ? $insights->sales($event) : null,
            'points' => $tab === 'points' ? $insights->points($event) : null,
            'boarding' => $tab === 'embarquement' ? $insights->boarding($event) : null,
            'transport' => $tab === 'transport' ? $insights->transport($event) : null,
            'incidents' => $tab === 'incidents' ? $insights->incidents($event) : null,
            'reviews' => $tab === 'avis' ? $insights->reviews($event) : null,
            'complaints' => $tab === 'reclamations' ? $insights->complaints($event) : null,
            'messages' => $tab === 'messages' ? $event->promoterMessages()->with('author')->latest()->get() : null,
            'cancellations' => $tab === 'annulations' ? $insights->cancellations($event) : null,
        ]);
    }
}
