@php($money = fn (int $amount): string => number_format($amount, 0, ',', ' ').' FCFA')
<div class="space-y-6">
    <div class="grid gap-4 lg:grid-cols-2">
        <x-ui.card>
            <p class="text-[13px] font-medium text-ink-secondary">Prochain reversement</p>
            @if ($next)
                <p class="mt-1 text-[30px] font-semibold tracking-tight tabular-nums">{{ $money($next->net) }}</p>
                <p class="text-[14px] text-ink-secondary">{{ $next->event->name }} · le {{ $next->due_on->translatedFormat('j F Y') }} · <x-ui.badge :tone="$next->status->tone()">{{ $next->status->label() }}</x-ui.badge></p>
                <x-ui.button class="mt-4" variant="secondary" size="sm" type="button" wire:click="show({{ $next->id }})">Voir le relevé</x-ui.button>
            @else
                <p class="mt-1 text-[15px] text-ink-tertiary">Aucun reversement en attente.</p>
            @endif
        </x-ui.card>
        <x-ui.card>
            <p class="text-[13px] font-medium text-ink-secondary">Événements en vente (estimation)</p>
            <ul class="mt-2 divide-y divide-hairline">
                @forelse ($upcoming as $row)
                    <li wire:key="upcoming-{{ $row['event']->id }}" class="flex items-center justify-between gap-3 py-2.5 text-[14px]">
                        <span><span class="font-medium">{{ $row['event']->name }}</span> <span class="text-ink-secondary">· vers le {{ $row['due_on']->translatedFormat('j F') }}</span></span>
                        <span class="tabular-nums">{{ $money($row['net']) }}</span>
                    </li>
                @empty
                    <li class="py-2.5 text-[14px] text-ink-tertiary">Aucun événement en vente.</li>
                @endforelse
            </ul>
            <p class="mt-2 text-[12px] text-ink-tertiary">Part compagnie des Pass vendus à ce jour, avant remboursements éventuels.</p>
        </x-ui.card>
    </div>

    <x-ui.table>
        <x-slot:head>
            <th>Relevé</th>
            <th>Événement</th>
            <th class="text-right">Recette brute</th>
            <th class="text-right">Net reversé</th>
            <th>Échéance</th>
            <th>Statut</th>
            <th></th>
        </x-slot:head>
        @forelse ($payouts as $payout)
            <tr wire:key="payout-{{ $payout->id }}">
                <td class="font-mono text-[13px] font-semibold">{{ $payout->reference }}</td>
                <td>{{ $payout->event->name }}</td>
                <td class="text-right tabular-nums">{{ $money($payout->gross) }}</td>
                <td class="text-right font-medium tabular-nums">{{ $money($payout->net) }}</td>
                <td class="whitespace-nowrap">{{ $payout->due_on->format('d/m/Y') }}</td>
                <td><x-ui.badge :tone="$payout->status->tone()">{{ $payout->status->label() }}</x-ui.badge></td>
                <td class="text-right"><x-ui.button variant="secondary" size="sm" type="button" wire:click="show({{ $payout->id }})">Détails</x-ui.button></td>
            </tr>
        @empty
            <tr><td colspan="7"><x-ui.empty-state icon="banknotes" title="Aucun reversement" description="Le relevé de chaque événement est établi après sa fin et versé 5 jours ouvrés plus tard." /></td></tr>
        @endforelse
    </x-ui.table>

    <x-ui.sheet model="showDetails" :title="$viewing ? 'Relevé '.$viewing->reference : 'Relevé'" :subtitle="$viewing?->event->name">
        @if ($viewing)
            @include('partials.payout-statement', ['payout' => $viewing])
            @if ($viewing->paid_at)
                <p class="mt-4 text-[14px]">Payé le {{ $viewing->paid_at->format('d/m/Y') }} · {{ $viewing->payment_method?->label() }} · {{ $viewing->payment_reference }}</p>
            @endif
            <div class="mt-5 flex flex-wrap gap-2">
                <x-ui.button variant="secondary" size="sm" icon="document" :href="route('company.payouts.statement', $viewing)">Relevé PDF</x-ui.button>
                @if ($viewing->proof_path)
                    <x-ui.button variant="secondary" size="sm" icon="document" :href="route('company.payouts.proof', $viewing)">Justificatif</x-ui.button>
                @endif
            </div>
        @endif
    </x-ui.sheet>
</div>
