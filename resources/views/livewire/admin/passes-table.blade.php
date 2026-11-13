@php($phone = fn (?string $value): string => $value ?? '—')
<div>
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <x-ui.search wire:model.live.debounce.400ms="search" placeholder="Code du Pass ou téléphone" />
        <div class="w-full sm:w-56">
            <x-ui.select label="Statut" name="status" wire:model.live="status" :options="$statuses" placeholder="Tous" />
        </div>
    </div>

    <x-ui.table>
        <x-slot:head>
            <th>Pass</th>
            <th>Événement</th>
            <th>Détenteur</th>
            <th>Point · offre</th>
            <th>Statut</th>
            <th></th>
        </x-slot:head>
        @forelse ($passes as $pass)
            <tr wire:key="pass-{{ $pass->id }}">
                <td class="font-mono text-[13px] font-semibold">{{ $pass->code }}</td>
                <td><p>{{ $pass->event->name }}</p><p class="text-[12px] text-ink-secondary">{{ $pass->event->starts_at->format('d/m/Y') }}</p></td>
                <td class="whitespace-nowrap">{{ $phone($pass->holder->phone) }}</td>
                <td><p>{{ $pass->boardingPoint->commune }}</p><p class="text-[12px] text-ink-secondary">{{ $pass->offer->label() }} · {{ $pass->pass_type->label() }}</p></td>
                <td><x-ui.badge :tone="$pass->status->tone()">{{ $pass->status->label() }}</x-ui.badge></td>
                <td class="text-right"><x-ui.button variant="secondary" size="sm" type="button" wire:click="show({{ $pass->id }})">Détails</x-ui.button></td>
            </tr>
        @empty
            <tr><td colspan="6"><x-ui.empty-state icon="ticket" title="Aucun Pass" description="Cherchez un Pass par son code (ET-…) ou par le téléphone de son détenteur." /></td></tr>
        @endforelse
        @if ($passes->hasPages())
            <x-slot:footer>{{ $passes->links() }}</x-slot:footer>
        @endif
    </x-ui.table>

    <x-ui.sheet model="showDetails" :title="$viewing ? 'Pass '.$viewing->code : 'Pass'" :subtitle="$viewing?->event->name">
        @if ($viewing)
            <div class="space-y-6 text-[14px]">
                <dl class="grid grid-cols-2 gap-4">
                    <div><dt class="text-ink-secondary">Statut</dt><dd><x-ui.badge :tone="$viewing->status->tone()">{{ $viewing->status->label() }}</x-ui.badge></dd></div>
                    <div><dt class="text-ink-secondary">Détenteur</dt><dd>{{ $phone($viewing->holder->phone) }}</dd></div>
                    <div><dt class="text-ink-secondary">Acheteur</dt><dd>{{ $phone($viewing->order->customer->phone) }} · {{ $viewing->order->reference }}</dd></div>
                    <div><dt class="text-ink-secondary">Prix</dt><dd>{{ number_format($viewing->price + $viewing->fee_amount, 0, ',', ' ') }} FCFA</dd></div>
                    <div><dt class="text-ink-secondary">Point · offre</dt><dd>{{ $viewing->boardingPoint->commune }} · {{ $viewing->offer->label() }}</dd></div>
                    <div><dt class="text-ink-secondary">Bus aller · retour</dt><dd>{{ $viewing->outboundMission?->bus?->number ?? '—' }} · {{ $viewing->returnMission?->bus?->number ?? '—' }}</dd></div>
                    <div><dt class="text-ink-secondary">Version du QR</dt><dd>{{ $viewing->token_version }}</dd></div>
                    <div><dt class="text-ink-secondary">Avis</dt><dd>{{ $viewing->review ? number_format($viewing->review->average(), 1, ',', ' ').' / 5' : '—' }}</dd></div>
                </dl>

                <section>
                    <h3 class="font-semibold">Historique des scans</h3>
                    <ul class="mt-2 divide-y divide-hairline">
                        @forelse ($viewing->scans as $scan)
                            <li wire:key="scan-{{ $scan->id }}" class="flex items-center justify-between gap-3 py-2">
                                <span>
                                    {{ $scan->scanned_at->format('d/m H\hi') }} · {{ $scan->segment->label() }} · Bus {{ $scan->mission->bus?->number ?? '?' }} ({{ $scan->mission->boardingPoint->commune }})
                                    @if ($scan->offline)<span class="text-ink-tertiary">· hors ligne</span>@endif
                                </span>
                                <x-ui.badge :tone="$scan->result->value === 'vert' ? 'success' : ($scan->result->value === 'orange' ? 'warning' : 'danger')">{{ $scan->reason?->label() ?? ucfirst($scan->result->value) }}</x-ui.badge>
                            </li>
                        @empty
                            <li class="py-2 text-ink-tertiary">Jamais scanné.</li>
                        @endforelse
                    </ul>
                </section>

                <section>
                    <h3 class="font-semibold">Transferts</h3>
                    <ul class="mt-2 divide-y divide-hairline">
                        @forelse ($viewing->transfers as $transfer)
                            <li wire:key="transfer-{{ $transfer->id }}" class="py-2">{{ $transfer->created_at->format('d/m H\hi') }} · {{ $phone($transfer->from->phone) }} → {{ $phone($transfer->to->phone) }}</li>
                        @empty
                            <li class="py-2 text-ink-tertiary">Aucun transfert.</li>
                        @endforelse
                    </ul>
                </section>

                @if ($viewing->complaints->isNotEmpty())
                    <section>
                        <h3 class="font-semibold">Réclamations</h3>
                        <ul class="mt-2 divide-y divide-hairline">
                            @foreach ($viewing->complaints as $complaint)
                                <li wire:key="pass-complaint-{{ $complaint->id }}" class="flex items-center justify-between gap-3 py-2">
                                    <span>{{ $complaint->number }} · {{ $complaint->title }}</span>
                                    <x-ui.badge :tone="$complaint->status->tone()">{{ $complaint->status->label() }}</x-ui.badge>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                @if ($viewing->isTransferable())
                    <form id="transfer-form" wire:submit="transfer" class="rounded-xl bg-fill p-4">
                        <x-ui.input label="Transférer à un autre numéro (à la demande du détenteur)" name="transferPhone" type="tel" wire:model="transferPhone" placeholder="07 00 00 00 00" />
                    </form>
                @endif
                <p class="text-[12px] text-ink-tertiary">L'annulation et le remboursement suivent les règles de la section 11 (bientôt disponibles).</p>
            </div>
        @endif
        <x-slot:footer>
            <x-ui.button variant="secondary" type="button" x-on:click="open = false">Fermer</x-ui.button>
            @if ($viewing?->isTransferable())
                <x-ui.button form="transfer-form" wire:confirm="Transférer ce Pass ? L'ancien QR ne fonctionnera plus.">Transférer</x-ui.button>
            @endif
        </x-slot:footer>
    </x-ui.sheet>
</div>
