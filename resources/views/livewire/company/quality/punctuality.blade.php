<div>
    @include('livewire.company.quality.partials.event-bar')

    @if ($event)
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <x-ui.segmented label="Sens">
                <x-ui.segment :active="$direction === 'aller'" wire:click="$set('direction', 'aller')">Aller</x-ui.segment>
                <x-ui.segment :active="$direction === 'retour'" wire:click="$set('direction', 'retour')">Retour</x-ui.segment>
            </x-ui.segmented>
            <p class="text-[14px] text-ink-secondary">
                {{ $summary['on_time_rate'] !== null ? $summary['on_time_rate'].' % à l\'heure' : 'Aucun départ confirmé' }} · tolérance {{ \App\Services\Reporting\QualityMetrics::tolerance() }} min
            </p>
        </div>
    @endif

    <x-ui.table>
        <x-slot:head>
            <th>Point</th>
            <th>Offre</th>
            <th>Bus</th>
            <th>Prévu</th>
            <th>Réel</th>
            <th>Écart</th>
        </x-slot:head>
        @forelse ($rows as $row)
            @php($mission = $row['mission'])
            <tr wire:key="departure-{{ $mission->id }}">
                <td>{{ $mission->boardingPoint->commune }} · {{ $mission->boardingPoint->place }}</td>
                <td>{{ $mission->offer->label() }}</td>
                <td>{{ $mission->bus ? 'Bus '.$mission->bus->number : '—' }}@if ($mission->replaced_by_mission_id) <x-ui.badge tone="danger">remplacé</x-ui.badge>@endif</td>
                <td class="tabular-nums">{{ $mission->planned_departure_at->format('H\hi') }}</td>
                <td class="tabular-nums">{{ $mission->actual_departure_at?->format('H\hi') ?? '—' }}</td>
                <td><x-ui.badge :tone="$row['on_time'] === null ? 'neutral' : ($row['on_time'] ? 'success' : 'warning')">{{ $row['gap_label'] }}</x-ui.badge></td>
            </tr>
        @empty
            <tr><td colspan="6"><x-ui.empty-state icon="clock" title="Aucun départ" description="Les départs apparaissent quand le plan de transport est généré." /></td></tr>
        @endforelse
    </x-ui.table>

    @if ($breakdowns->isNotEmpty())
        <x-ui.card class="mt-6">
            <h2 class="text-[17px] font-semibold tracking-tight">Pannes de l'événement</h2>
            <ul class="mt-3 divide-y divide-hairline">
                @foreach ($breakdowns as $alert)
                    <li wire:key="breakdown-{{ $alert->id }}" class="flex flex-wrap items-center justify-between gap-2 py-2.5 text-[14px]">
                        <span><span class="font-medium">{{ $alert->created_at->format('H\hi') }} · Bus {{ $alert->bus?->number ?? '?' }}</span> <span class="text-ink-secondary">· {{ $alert->mission->boardingPoint->commune }} · {{ $alert->type->label() }}</span></span>
                        <x-ui.badge :tone="$alert->status->tone()">{{ $alert->intervention?->result?->label() ?? $alert->status->label() }}</x-ui.badge>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
    @endif
</div>
