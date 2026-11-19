<div>
    <x-ui.table>
        <x-slot:head>
            <th>Événement</th>
            <th>Statut</th>
            <th class="text-right">Pass vendus</th>
            <th class="text-right">Documents</th>
        </x-slot:head>
        @forelse ($events as $event)
            <tr wire:key="report-{{ $event->id }}">
                <td>
                    <p class="font-medium">{{ $event->name }}</p>
                    <p class="text-[13px] text-ink-secondary">{{ $event->starts_at->translatedFormat('j F Y') }}{{ $event->venue ? ' · '.$event->venue->name : '' }}</p>
                </td>
                <td><x-ui.badge :tone="$event->status->tone()">{{ $event->status->label() }}</x-ui.badge></td>
                <td class="text-right tabular-nums">{{ number_format($event->sold_count, 0, ',', ' ') }}</td>
                <td class="text-right">
                    <div class="flex flex-wrap justify-end gap-2">
                        <x-ui.button variant="secondary" size="sm" icon="document" :href="route('company.reports.sales', $event)">Ventes (Excel)</x-ui.button>
                        @if (in_array($event->status, [\App\Enums\EventStatus::OnSale, \App\Enums\EventStatus::Finished], true) && auth()->user()->can(\App\Enums\Permission::QualityView->value))
                            <x-ui.button variant="secondary" size="sm" icon="document" :href="route('company.quality.report', ['event' => $event->id])">Qualité (PDF)</x-ui.button>
                        @endif
                        @if ($event->payout && auth()->user()->can(\App\Enums\Permission::CompanyPayoutsView->value))
                            <x-ui.button variant="secondary" size="sm" icon="document" :href="route('company.payouts.statement', $event->payout)">Relevé de reversement (PDF)</x-ui.button>
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="4"><x-ui.empty-state icon="document" title="Aucun rapport" description="Les rapports sont disponibles dès l'ouverture de la vente d'un événement." /></td></tr>
        @endforelse
        @if ($events->hasPages())
            <x-slot:footer>{{ $events->links() }}</x-slot:footer>
        @endif
    </x-ui.table>
</div>
