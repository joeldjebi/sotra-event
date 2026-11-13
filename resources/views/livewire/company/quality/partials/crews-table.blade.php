@php($rating = fn (?float $value): string => \App\Services\Reporting\QualityMetrics::rating($value))
<x-ui.table class="!rounded-none !shadow-none !ring-0">
    <x-slot:head>
        <th>Bus</th>
        <th>Point</th>
        <th>Chauffeur</th>
        <th>Convoyeur</th>
        <th>Départ aller</th>
        <th class="text-right">Avis</th>
    </x-slot:head>
    @forelse ($crews as $crew)
        @php($mission = $crew['mission'])
        <tr wire:key="crew-{{ $mission->id }}">
            <td class="font-semibold">Bus {{ $mission->bus?->number }}</td>
            <td>{{ $mission->boardingPoint->commune }}</td>
            <td>{{ $mission->driver?->fullName() ?? '—' }} @if ($crew['driver_rating'] !== null)<span class="text-ink-secondary">· {{ $rating($crew['driver_rating']) }} ★</span>@endif</td>
            <td>{{ $mission->conveyor?->fullName() ?? '—' }} @if ($crew['conveyor_rating'] !== null)<span class="text-ink-secondary">· {{ $rating($crew['conveyor_rating']) }} ★</span>@endif</td>
            <td class="whitespace-nowrap">
                {{ $mission->actual_departure_at?->format('H\hi') ?? '—' }}
                <x-ui.badge :tone="$crew['on_time'] === null ? 'neutral' : ($crew['on_time'] ? 'success' : 'warning')">{{ $crew['gap_label'] }}</x-ui.badge>
            </td>
            <td class="text-right tabular-nums">{{ $crew['reviews'] }}</td>
        </tr>
    @empty
        <tr><td colspan="6"><x-ui.empty-state icon="users" title="Aucun équipage" description="Les équipages apparaissent quand les bus sont affectés aux missions." /></td></tr>
    @endforelse
</x-ui.table>
