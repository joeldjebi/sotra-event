@php($money = fn (int $amount): string => number_format($amount, 0, ',', ' ').' FCFA')
<div>
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <x-ui.segmented label="Vue">
            <x-ui.segment :active="$view === 'manuel'" wire:click="$set('view', 'manuel')">À traiter manuellement ({{ $counts['manual'] }})</x-ui.segment>
            @foreach ($statuses as $value => $label)
                <x-ui.segment wire:key="status-{{ $value }}" :active="$view === $value" wire:click="$set('view', '{{ $value }}')">{{ $label }}</x-ui.segment>
            @endforeach
            <x-ui.segment :active="$view === 'tous'" wire:click="$set('view', 'tous')">Tous</x-ui.segment>
        </x-ui.segmented>
        @if ($counts['overdue'] > 0)
            <x-ui.badge tone="danger" class="px-3 py-1 text-[13px]">{{ $counts['overdue'] }} hors délai</x-ui.badge>
        @endif
    </div>

    <x-ui.table>
        <x-slot:head>
            <th>Remboursement</th>
            <th>Pass · événement</th>
            <th>Spectateur</th>
            <th class="text-right">Montant</th>
            <th>Échéance</th>
            <th>Statut</th>
            <th></th>
        </x-slot:head>
        @forelse ($refunds as $refund)
            <tr wire:key="refund-{{ $refund->id }}">
                <td>
                    <p class="font-mono text-[13px] font-semibold">{{ $refund->reference }}</p>
                    <p class="text-[12px] text-ink-secondary">{{ $refund->reason->label() }}</p>
                </td>
                <td><p class="font-mono text-[13px]">{{ $refund->pass->code }}</p><p class="text-[12px] text-ink-secondary">{{ $refund->event->name }}</p></td>
                <td class="whitespace-nowrap"><p>{{ $refund->customer->phone }}</p><p class="text-[12px] text-ink-secondary">{{ $refund->payment->method->label() }}</p></td>
                <td class="text-right whitespace-nowrap tabular-nums">
                    <p class="font-medium">{{ $money($refund->amount) }}</p>
                    @if ($refund->retained_amount > 0)<p class="text-[12px] text-ink-secondary">{{ $money($refund->retained_amount) }} retenus</p>@endif
                </td>
                <td @class(['whitespace-nowrap', 'font-medium text-danger' => $refund->isOverdue()])>{{ $refund->due_at->format('d/m H\hi') }}</td>
                <td>
                    <x-ui.badge :tone="$refund->status->tone()">{{ $refund->status->label() }}</x-ui.badge>
                    <p class="mt-0.5 text-[12px] text-ink-secondary">{{ $refund->mode->label() }}@if ($refund->provider_reference) · {{ $refund->provider_reference }}@endif</p>
                    @if ($refund->failure_reason && $refund->status->value !== 'rembourse')<p class="text-[12px] text-ink-tertiary">{{ $refund->failure_reason }}</p>@endif
                </td>
                <td class="text-right whitespace-nowrap">
                    @if ($refund->status->value !== 'rembourse')
                        @if ($refund->mode->value === 'api' || $refund->status->value === 'echec')
                            <x-ui.button variant="ghost" size="sm" type="button" wire:click="retry({{ $refund->id }})">Relancer</x-ui.button>
                        @endif
                        <x-ui.button variant="secondary" size="sm" type="button" wire:click="openManual({{ $refund->id }})">Marquer remboursé</x-ui.button>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="7"><x-ui.empty-state icon="banknotes" title="Aucun remboursement" description="Les remboursements que l'agrégateur ne peut pas faire par API apparaissent ici pour un traitement manuel." /></td></tr>
        @endforelse
        @if ($refunds->hasPages())
            <x-slot:footer>{{ $refunds->links() }}</x-slot:footer>
        @endif
    </x-ui.table>

    <x-ui.sheet model="showManual" :title="$processing ? 'Remboursement '.$processing->reference : 'Remboursement'" :subtitle="$processing ? $money($processing->amount).' sur '.$processing->payment->method->label().' · '.$processing->customer->phone : null">
        <form id="manual-form" wire:submit="markRefunded" class="space-y-4">
            <p class="text-[14px] text-ink-secondary">Effectuez le remboursement depuis l'espace marchand de l'opérateur, puis saisissez la référence de l'opération. Le spectateur est prévenu par SMS.</p>
            <x-ui.input label="Référence de l'opération" name="operationReference" wire:model="operationReference" placeholder="Ex. : MP231115.1043.A12345" />
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" type="button" x-on:click="open = false">Annuler</x-ui.button>
            <x-ui.button form="manual-form">Marquer remboursé</x-ui.button>
        </x-slot:footer>
    </x-ui.sheet>
</div>
