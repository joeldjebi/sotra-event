@php($money = fn (int $amount): string => number_format($amount, 0, ',', ' ').' FCFA')
<div class="space-y-6">
    <x-ui.card>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-[19px] font-semibold tracking-tight">{{ $event->name }}</h2>
                <p class="text-[14px] text-ink-secondary">{{ ucfirst($event->starts_at->translatedFormat('l j F Y à H\hi')) }} · {{ $validPasses }} Pass valides · {{ $money($paidTotal) }} encaissés (frais compris)</p>
                @if ($event->postponed_at)
                    <p class="mt-1 text-[13px] text-warning">Reporté le {{ $event->postponed_at->format('d/m/Y') }} (date initiale : {{ $event->original_starts_at?->translatedFormat('j F Y à H\hi') }}).</p>
                @endif
            </div>
            <x-ui.badge :tone="$event->status->tone()" class="px-3 py-1 text-[13px]">{{ $event->status->label() }}</x-ui.badge>
        </div>
        @if ($refunds->isNotEmpty())
            <p class="mt-3 text-[13px] text-ink-secondary">
                Remboursements :
                @foreach ($refunds as $row)
                    {{ $row->status->label() }} {{ $row->total }} ({{ $money((int) $row->amount) }})@if (! $loop->last) · @endif
                @endforeach
                · <a href="{{ route('admin.refunds') }}" class="font-medium text-brand-text hover:underline">Suivre les remboursements</a>
            </p>
        @endif
    </x-ui.card>

    @if (! $active)
        <x-ui.alert tone="warning">Aucune action possible : l'événement est « {{ $event->status->label() }} ».</x-ui.alert>
    @else
        <div class="grid gap-6 xl:grid-cols-3">
            <x-ui.card>
                <h3 class="text-[17px] font-semibold tracking-tight">Reporter l'événement</h3>
                <p class="mt-1 text-[13px] text-ink-secondary">Tous les horaires (points, zones, bus) sont décalés. Les Pass restent valables ; le remboursement intégral, sans frais, reste possible sur demande (RG-18).</p>
                <form wire:submit="postpone" class="mt-4 space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <x-ui.input label="Nouvelle date" name="newDate" type="date" wire:model="newDate" />
                        <x-ui.input label="Heure" name="newTime" type="time" wire:model="newTime" />
                    </div>
                    <x-ui.button variant="secondary" class="w-full" wire:confirm="Reporter l'événement et prévenir {{ $validPasses }} détenteurs de Pass ?">Reporter et prévenir</x-ui.button>
                </form>
            </x-ui.card>

            <x-ui.card>
                <h3 class="text-[17px] font-semibold tracking-tight">Annuler un départ</h3>
                <p class="mt-1 text-[13px] text-ink-secondary">Les Pass du point sont remboursés intégralement, frais compris (RG-17), et la vente s'arrête sur ce point.</p>
                <form wire:submit="cancelDeparture" class="mt-4 space-y-3">
                    <x-ui.select label="Départ" name="departureId" wire:model="departureId" placeholder="Choisir"
                                 :options="$points->filter(fn ($point) => $point->cancelled_at === null)->mapWithKeys(fn ($point) => [$point->id => $point->commune.' · '.$point->place.' · '.$point->valid_passes_count.' Pass'])->all()" />
                    <x-ui.input label="Motif" name="departureReason" wire:model="departureReason" placeholder="Ex. : route impraticable" />
                    <x-ui.button variant="secondary" class="w-full" wire:confirm="Annuler ce départ et rembourser ses Pass ?">Annuler le départ</x-ui.button>
                </form>
                @foreach ($points->whereNotNull('cancelled_at') as $point)
                    <p wire:key="cancelled-{{ $point->id }}" class="mt-3 text-[13px] text-danger">{{ $point->commune }} annulé le {{ $point->cancelled_at->format('d/m H\hi') }} · {{ $point->cancellation_reason }}</p>
                @endforeach
            </x-ui.card>

            <x-ui.card class="ring-1 ring-danger/30">
                <h3 class="text-[17px] font-semibold tracking-tight text-danger">Annuler l'événement</h3>
                <p class="mt-1 text-[13px] text-ink-secondary">Les {{ $validPasses }} Pass valides sont remboursés intégralement, frais compris ({{ $money($paidTotal) }}), sur le moyen de paiement d'origine (RG-17). Action définitive.</p>
                <form wire:submit="cancelEvent" class="mt-4 space-y-3">
                    <x-ui.input label="Motif communiqué aux spectateurs" name="cancelReason" wire:model="cancelReason" placeholder="Ex. : décision des autorités" />
                    <x-ui.button variant="danger" class="w-full" wire:confirm="Annuler définitivement l'événement et rembourser {{ $validPasses }} Pass ?">Annuler l'événement</x-ui.button>
                </form>
            </x-ui.card>
        </div>
    @endif
</div>
