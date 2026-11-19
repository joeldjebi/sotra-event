{{-- Onglet partagé (espace promoteur, supervision ACCES TRANS) : volumes seulement. --}}
@php($fmt ??= fn (int $value): string => number_format($value, 0, ',', ' '))
<div class="mb-6 grid gap-4 sm:grid-cols-3">
    <x-ui.kpi label="Départs confirmés" :value="$fmt($transport['departed']).' / '.$fmt($transport['planned'])" icon="bus" />
    <x-ui.kpi label="Départs à l'heure" :value="$transport['on_time_rate'] === null ? '—' : $transport['on_time_rate'].' %'" :hint="'Tolérance de '.\App\Services\Reporting\QualityMetrics::tolerance().' min'" icon="clock" />
    <x-ui.kpi label="Bus arrivés" :value="$fmt($transport['arrived'])" icon="map-pin" />
</div>
<x-ui.table>
    <x-slot:head>
        <th>Sens · point</th><th>Offre</th><th>Bus</th><th class="text-right">Passagers</th>
        <th>Départ prévu · réel</th><th>Écart</th><th>Arrivée</th><th>Statut</th>
    </x-slot:head>
    @forelse ($transport['rows'] as $row)
        <tr wire:key="mission-{{ $loop->index }}" @class(['opacity-55' => $row['replaced']])>
            <td><p class="font-medium">{{ $row['direction']->label() }}</p><p class="text-[12px] text-ink-secondary">{{ $row['point'] }}</p></td>
            <td>{{ $row['offer']->label() }}</td>
            <td>{{ $row['bus'] ? 'Bus '.$row['bus'] : 'À affecter' }}@if ($row['replaced'])<p class="text-[12px] text-ink-secondary">Remplacé après panne</p>@endif</td>
            <td class="text-right tabular-nums">{{ $fmt($row['boarded']) }} <span class="text-ink-tertiary">/ {{ $fmt($row['capacity']) }}</span></td>
            <td class="tabular-nums">{{ $row['planned_at']->format('H\hi') }} · {{ $row['actual_at']?->format('H\hi') ?? '—' }}</td>
            <td>
                @if ($row['on_time'] === null)
                    <span class="text-ink-tertiary">{{ $row['gap_label'] }}</span>
                @else
                    <x-ui.badge :tone="$row['on_time'] ? 'success' : 'warning'">{{ $row['gap_label'] }}</x-ui.badge>
                @endif
            </td>
            <td class="tabular-nums">{{ $row['arrived_at']?->format('H\hi') ?? '—' }}</td>
            <td><x-ui.badge :tone="$row['status']->tone()">{{ $row['status']->label() }}</x-ui.badge></td>
        </tr>
    @empty
        <tr><td colspan="8"><x-ui.empty-state icon="bus" title="Plan de transport à venir" description="La compagnie répartit les bus selon les ventes, avant le jour J." /></td></tr>
    @endforelse
</x-ui.table>
