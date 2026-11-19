<div>
    <div class="mb-5 flex flex-wrap items-center gap-3">
        <x-ui.search wire:model.live.debounce.300ms="search" placeholder="Rechercher un événement" />
        <x-ui.segmented label="Période">
            @foreach (['a-venir' => 'À venir', 'termines' => 'Terminés', 'tous' => 'Tous'] as $value => $label)
                <x-ui.segment wire:key="period-{{ $value }}" :active="$period === $value" wire:click="$set('period', '{{ $value }}')">{{ $label }}</x-ui.segment>
            @endforeach
        </x-ui.segmented>
    </div>

    <x-ui.table>
        <x-slot:head>
            <th>Événement</th>
            <th>Date</th>
            <th>Transporteur</th>
            <th class="text-right">Pass vendus</th>
            <th>Remplissage</th>
            <th>Statut</th>
            <th></th>
        </x-slot:head>

        @forelse ($events as $event)
            @php
                $capacity = (int) $event->offers->sum(fn ($offer) => (int) $offer->capacity());
                $passes = $sold[$event->id] ?? 0;
                $rate = $capacity > 0 ? (int) round($passes / $capacity * 100) : null;
            @endphp
            <tr wire:key="event-{{ $event->id }}" class="hover:bg-fill-subtle">
                <td>
                    <a href="{{ route('promoter.events.show', $event) }}" class="font-medium hover:text-brand-text">{{ $event->name }}</a>
                    <p class="text-[12px] text-ink-secondary">{{ $event->venue->name }}</p>
                </td>
                <td class="whitespace-nowrap">{{ ucfirst($event->starts_at->translatedFormat('D j M Y · H\hi')) }}</td>
                <td class="text-ink-secondary">{{ $event->company->name }}</td>
                <td class="text-right tabular-nums">{{ number_format($passes, 0, ',', ' ') }} <span class="text-ink-tertiary">/ {{ number_format($capacity, 0, ',', ' ') }}</span></td>
                <td class="w-40">
                    @if ($rate !== null)
                        <div class="flex items-center gap-2">
                            <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-fill"><div class="h-full rounded-full bg-brand" style="width: {{ min(100, $rate) }}%"></div></div>
                            <span class="w-9 text-right text-[13px] tabular-nums">{{ $rate }} %</span>
                        </div>
                    @else
                        <span class="text-[13px] text-ink-tertiary">Capacité à définir</span>
                    @endif
                </td>
                <td><x-ui.badge :tone="$event->status->tone()">{{ $event->status->label() }}</x-ui.badge></td>
                <td class="text-right"><x-ui.button variant="secondary" size="sm" :href="route('promoter.events.show', $event)">Détails</x-ui.button></td>
            </tr>
        @empty
            <tr><td colspan="7"><x-ui.empty-state icon="calendar" title="Aucun événement" description="Vos événements apparaissent ici dès que la compagnie de transport les a acceptés." /></td></tr>
        @endforelse

        @if ($events->hasPages())
            <x-slot:footer>{{ $events->links() }}</x-slot:footer>
        @endif
    </x-ui.table>
</div>
