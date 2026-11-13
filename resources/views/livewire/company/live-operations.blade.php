<div wire:poll.10s class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div class="flex flex-wrap items-end gap-3">
            <div class="w-full sm:w-80"><x-ui.select label="Événement" name="eventId" wire:model.live="eventId" :options="$eventOptions" placeholder="Choisir un événement" /></div>
            <x-ui.segmented label="Sens">
                <x-ui.segment :active="$direction === 'aller'" wire:click="$set('direction', 'aller')">Aller</x-ui.segment>
                <x-ui.segment :active="$direction === 'retour'" wire:click="$set('direction', 'retour')">Retour</x-ui.segment>
            </x-ui.segmented>
        </div>
        @if ($event)
            <div class="flex flex-wrap gap-2">
                <x-ui.badge tone="success" class="px-3 py-1 text-[13px]">{{ $counts['en_route'] }} en route</x-ui.badge>
                <x-ui.badge tone="warning" class="px-3 py-1 text-[13px]">{{ $counts['embarquement'] }} en embarquement</x-ui.badge>
                <x-ui.badge tone="danger" class="px-3 py-1 text-[13px]">{{ $counts['panne'] }} {{ \Illuminate\Support\Str::plural('panne', $counts['panne']) }}</x-ui.badge>
                <x-ui.badge class="px-3 py-1 text-[13px]">{{ $counts['arrivee'] }} arrivés</x-ui.badge>
                <x-ui.badge class="px-3 py-1 text-[13px]">{{ $counts['reserve'] }} en réserve</x-ui.badge>
            </div>
        @endif
    </div>

    @if (! $event)
        <x-ui.card padding="p-0"><x-ui.empty-state icon="map" title="Aucun événement en cours" description="Le suivi en direct s'affiche pour les événements en vente de votre compagnie." /></x-ui.card>
    @else
        <x-ui.map :data="$map" :label="'Carte des bus · '.$event->name" :height="420" />

        <x-ui.table>
            <x-slot:head>
                <th>Bus</th>
                <th>Point</th>
                <th>Chauffeur</th>
                <th>Convoyeur</th>
                <th class="text-right">À bord</th>
                <th>Statut</th>
                <th>Position</th>
            </x-slot:head>
            @forelse ($rows as $row)
                @php($mission = $row['mission'])
                <tr wire:key="live-{{ $mission->id }}">
                    <td class="font-semibold whitespace-nowrap">{{ $mission->bus ? 'Bus '.$mission->bus->number : '—' }} <span class="text-[12px] font-normal text-ink-tertiary">{{ $mission->offer->label() }}</span></td>
                    <td>{{ $mission->boardingPoint->commune }}</td>
                    <td>{{ $mission->driver?->shortName() ?? '—' }}</td>
                    <td>{{ $mission->conveyor?->shortName() ?? '—' }}</td>
                    <td class="text-right tabular-nums">{{ $row['on_board'] }} / {{ $mission->capacity() }}</td>
                    <td><x-ui.badge :tone="$mission->status->tone()">{{ $mission->status->label() }}</x-ui.badge></td>
                    <td class="text-[13px] text-ink-secondary whitespace-nowrap">
                        @if ($row['position'])
                            {{ $row['eta'] !== null ? '≈ '.$row['eta'].' min de l\'arrivée' : 'Arrivé' }}
                            <span class="text-ink-tertiary">· {{ $row['position']->recorded_at->diffForHumans() }}</span>
                        @elseif ($mission->actual_departure_at)
                            Parti à {{ $mission->actual_departure_at->format('H\hi') }}, sans GPS
                        @else
                            Départ prévu {{ $mission->planned_departure_at->format('H\hi') }}
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7"><x-ui.empty-state icon="route" title="Aucune mission" description="Générez le plan de transport pour voir les bus ici." /></td></tr>
            @endforelse
        </x-ui.table>
    @endif
</div>
