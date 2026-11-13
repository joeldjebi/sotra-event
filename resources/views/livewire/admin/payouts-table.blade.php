@php($money = fn (int $amount): string => number_format($amount, 0, ',', ' ').' FCFA')
<div>
    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-ui.kpi label="À valider" :value="$money($totals['to_validate'])" icon="document" />
        <x-ui.kpi label="À verser" :value="$money($totals['to_pay'])" :tone="$totals['to_pay'] > 0 ? 'warning' : 'default'" icon="banknotes" />
        <x-ui.kpi label="Versé" :value="$money($totals['paid'])" tone="success" icon="check-circle" />
    </div>

    <div class="mb-5 flex flex-wrap items-end gap-3">
        <div class="w-full sm:w-56"><x-ui.select label="Statut" name="status" wire:model.live="status" :options="$statuses" placeholder="Tous" /></div>
        <div class="w-full sm:w-64"><x-ui.select label="Compagnie" name="company" wire:model.live="company" :options="$companies" placeholder="Toutes" /></div>
    </div>

    <x-ui.table>
        <x-slot:head>
            <th>Relevé</th>
            <th>Événement</th>
            <th>Compagnie</th>
            <th class="text-right">Net à reverser</th>
            <th>Échéance</th>
            <th>Statut</th>
            <th></th>
        </x-slot:head>
        @forelse ($payouts as $payout)
            <tr wire:key="payout-{{ $payout->id }}">
                <td class="font-mono text-[13px] font-semibold">{{ $payout->reference }}</td>
                <td><p>{{ $payout->event->name }}</p><p class="text-[12px] text-ink-secondary">{{ $payout->event->starts_at->format('d/m/Y') }}</p></td>
                <td>{{ $payout->company->name }}</td>
                <td class="text-right font-medium tabular-nums">{{ $money($payout->net) }}</td>
                <td @class(['whitespace-nowrap', 'font-medium text-danger' => $payout->status->value !== 'paye' && $payout->due_on->isPast()])>{{ $payout->due_on->format('d/m/Y') }}</td>
                <td><x-ui.badge :tone="$payout->status->tone()">{{ $payout->status->label() }}</x-ui.badge></td>
                <td class="text-right"><x-ui.button variant="secondary" size="sm" type="button" wire:click="show({{ $payout->id }})">Ouvrir</x-ui.button></td>
            </tr>
        @empty
            <tr><td colspan="7"><x-ui.empty-state icon="banknotes" title="Aucun relevé" description="Les relevés sont calculés chaque nuit pour les événements terminés." /></td></tr>
        @endforelse
        @if ($payouts->hasPages())
            <x-slot:footer>{{ $payouts->links() }}</x-slot:footer>
        @endif
    </x-ui.table>

    <x-ui.sheet model="showDetails" :title="$viewing ? 'Relevé '.$viewing->reference : 'Relevé'" :subtitle="$viewing ? $viewing->event->name.' · '.$viewing->company->name : null">
        @if ($viewing)
            <div class="space-y-6">
                @include('partials.payout-statement', ['payout' => $viewing])

                <div class="flex flex-wrap gap-2">
                    <x-ui.button variant="secondary" size="sm" icon="document" :href="route('admin.payouts.statement', $viewing)">Relevé PDF</x-ui.button>
                    @if ($viewing->proof_path)
                        <x-ui.button variant="secondary" size="sm" icon="document" :href="route('admin.payouts.proof', $viewing)">Justificatif</x-ui.button>
                    @endif
                    @if ($viewing->status->value === 'calcule')
                        <x-ui.button variant="ghost" size="sm" type="button" wire:click="recalculate">Recalculer</x-ui.button>
                    @endif
                </div>

                <ol class="space-y-1.5 text-[13px] text-ink-secondary">
                    <li>Calculé le {{ $viewing->calculated_at->format('d/m/Y H\hi') }}</li>
                    @if ($viewing->validated_at)<li>Validé le {{ $viewing->validated_at->format('d/m/Y H\hi') }} par {{ $viewing->validator?->name }}</li>@endif
                    @if ($viewing->paid_at)<li>Payé le {{ $viewing->paid_at->format('d/m/Y H\hi') }} par {{ $viewing->payer?->name }} · {{ $viewing->payment_method?->label() }} · {{ $viewing->payment_reference }}</li>@endif
                </ol>

                @if ($viewing->status->value === 'valide')
                    <form id="pay-form" wire:submit="markPaid" class="space-y-4 rounded-xl bg-fill p-4">
                        <h3 class="text-[15px] font-semibold">Enregistrer le paiement</h3>
                        <x-ui.select label="Mode" name="paymentMethod" wire:model="paymentMethod" :options="$methods" />
                        <x-ui.input label="Référence du virement ou de la transaction" name="paymentReference" wire:model="paymentReference" />
                        <div>
                            <label for="proof" class="block px-0.5 text-footnote font-medium text-ink-secondary">Justificatif (PDF ou image)</label>
                            <input id="proof" type="file" wire:model="proof" accept=".pdf,image/*" class="mt-1.5 block w-full text-[14px]">
                            @error('proof')<p class="mt-1 text-[13px] text-danger">{{ $message }}</p>@enderror
                        </div>
                    </form>
                @endif
            </div>
        @endif
        <x-slot:footer>
            <x-ui.button variant="secondary" type="button" x-on:click="open = false">Fermer</x-ui.button>
            @if ($viewing?->status->value === 'calcule')
                <x-ui.button type="button" wire:click="validatePayout" wire:confirm="Valider ce relevé ? Il ne sera plus recalculé.">Valider le relevé</x-ui.button>
            @elseif ($viewing?->status->value === 'valide')
                <x-ui.button form="pay-form">Marquer payé</x-ui.button>
            @endif
        </x-slot:footer>
    </x-ui.sheet>
</div>
