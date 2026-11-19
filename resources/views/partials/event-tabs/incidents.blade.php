{{-- Onglet partagé (espace promoteur, supervision ACCES TRANS) : volumes seulement. --}}
@php($fmt ??= fn (int $value): string => number_format($value, 0, ',', ' '))
<x-ui.table>
    <x-slot:head><th>Signalée</th><th>Bus · point</th><th>Panne</th><th class="text-right">Passagers à bord</th><th>Issue</th><th>Statut</th></x-slot:head>
    @forelse ($incidents as $row)
        <tr wire:key="incident-{{ $loop->index }}">
            <td class="whitespace-nowrap">{{ $row['at']->translatedFormat('j M · H\hi') }}</td>
            <td><p>{{ $row['bus'] ? 'Bus '.$row['bus'] : '—' }}</p><p class="text-[12px] text-ink-secondary">{{ $row['point'] ?? '' }}{{ $row['place'] ? ' · '.$row['place'] : '' }}</p></td>
            <td>{{ $row['type'] }} @if ($row['urgent'])<x-ui.badge tone="danger" class="ml-1">Urgent</x-ui.badge>@endif</td>
            <td class="text-right tabular-nums">{{ $fmt($row['passengers']) }}</td>
            <td>{{ $row['result'] ?? 'En cours' }}@if ($row['duration'] !== null)<p class="text-[12px] text-ink-secondary">Résolue en {{ $row['duration'] }} min</p>@endif</td>
            <td><x-ui.badge :tone="$row['status']->tone()">{{ $row['status']->label() }}</x-ui.badge></td>
        </tr>
    @empty
        <tr><td colspan="6"><x-ui.empty-state icon="wrench" title="Aucune panne" description="Les pannes signalées par les convoyeurs s'afficheront ici, avec leur prise en charge." /></td></tr>
    @endforelse
</x-ui.table>
