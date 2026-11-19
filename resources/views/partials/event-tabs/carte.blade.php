{{-- Onglet partagé (espace promoteur, supervision ACCES TRANS) : volumes seulement. --}}
@php($fmt ??= fn (int $value): string => number_format($value, 0, ',', ' '))
<div wire:poll.15s>
    @if ($live['tracked'] === 0)
        <x-ui.alert tone="info" class="mb-4">Aucun bus en mission pour le moment. Les positions s'affichent pendant les trajets, envoyées par le téléphone du convoyeur.</x-ui.alert>
    @endif
    <div class="grid gap-6 xl:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
        <x-ui.map :data="$live['map']" label="Carte des bus en direct" :height="460" wire:key="live-map-{{ $event->id }}" />
        <x-ui.table>
            <x-slot:head><th>Bus</th><th>Statut</th><th class="text-right">À bord</th><th class="text-right">Arrivée estimée</th></x-slot:head>
            @forelse ($live['buses'] as $bus)
                <tr wire:key="live-bus-{{ $loop->index }}">
                    <td><p class="font-medium">{{ $bus['label'] }}</p><p class="text-[12px] text-ink-secondary">{{ $bus['direction']->label() }} · {{ $bus['point'] }}{{ $bus['seen_at'] ? ' · vu '.$bus['seen_at']->diffForHumans() : '' }}</p></td>
                    <td><x-ui.badge :tone="$bus['status']->tone()">{{ $bus['status']->label() }}</x-ui.badge></td>
                    <td class="text-right tabular-nums">{{ $fmt($bus['passengers']) }}</td>
                    <td class="text-right tabular-nums">{{ $bus['eta'] === null ? '—' : 'dans '.$bus['eta'].' min' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-[14px] text-ink-secondary">Aucun bus en mission.</td></tr>
            @endforelse
        </x-ui.table>
    </div>
    <p class="mt-3 text-[12px] text-ink-tertiary">Mise à jour automatique toutes les 15 secondes. Les cercles sont les zones de dépose et de reprise.</p>
</div>
