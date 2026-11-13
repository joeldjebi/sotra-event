<div>
    <h1 class="text-[28px] leading-tight font-bold tracking-tight">Choisir mon Pass</h1>
    <p class="mt-1 text-[15px] text-ink-secondary">{{ $event->name }} · {{ $event->starts_at->translatedFormat('j M') }} · {{ $event->venue->name }}</p>

    <div class="mt-6 space-y-6">
        <x-ui.select label="Point de départ" name="pointId" wire:model.live="pointId" :options="$pointOptions" />
        @if ($point)
            <p class="-mt-4 text-[13px] text-ink-tertiary">Convocation {{ $point->boardingCallAt()->format('H\hi') }} · départ du bus {{ $point->departure_at->format('H\hi') }} · retour {{ $point->return_departure_at->format('H\hi') }}</p>
        @endif

        @if (count($availableTypes) > 1)
            <div>
                <p class="mb-1.5 text-[13px] font-medium text-ink-secondary">Trajet</p>
                <div @class(['grid rounded-xl bg-fill p-0.5 text-[14px] font-medium', 'grid-cols-2' => count($availableTypes) === 2, 'grid-cols-3' => count($availableTypes) === 3])>
                    @foreach ($availableTypes as $type)
                        <button type="button" wire:key="type-{{ $type->value }}" wire:click="$set('passType', '{{ $type->value }}')" @class(['rounded-[10px] py-2 transition', 'bg-surface shadow-sm' => $passType === $type->value, 'text-ink-secondary' => $passType !== $type->value])>{{ $type->label() }}</button>
                    @endforeach
                </div>
            </div>
        @endif

        <div>
            <p class="mb-1.5 text-[13px] font-medium text-ink-secondary">Offre ({{ mb_strtolower(\App\Enums\PassType::from($passType)->label()) }})</p>
            <div class="space-y-3">
                @foreach ($offers as $item)
                    @php($price = $item['prices'][$passType][$point?->zone] ?? null)
                    @php($remaining = $item['remaining'][$passType] ?? null)
                    @if ($price !== null)
                        <button type="button" wire:key="offer-{{ $item['offer']->value }}" wire:click="$set('offer', '{{ $item['offer']->value }}')" @disabled($remaining === 0)
                                @class(['flex w-full items-center justify-between gap-4 rounded-[22px] bg-surface p-5 text-left ring-1 transition disabled:opacity-50',
                                        'ring-2 ring-brand' => $offer === $item['offer']->value, 'ring-hairline hover:bg-canvas' => $offer !== $item['offer']->value])>
                            <span>
                                <span class="block text-[17px] font-semibold">{{ $item['offer']->label() }}</span>
                                <span class="block text-[14px] text-ink-secondary">{{ $item['offer']->tagline() }}</span>
                                @if ($remaining !== null && $remaining <= 20)
                                    <span class="mt-1 block text-[13px] font-medium {{ $remaining === 0 ? 'text-danger' : 'text-warning' }}">{{ $remaining === 0 ? 'Complet' : "Plus que {$remaining} place(s)" }}</span>
                                @endif
                            </span>
                            <span class="text-[17px] font-semibold whitespace-nowrap tabular-nums">{{ number_format($price, 0, ',', ' ') }} FCFA</span>
                        </button>
                    @endif
                @endforeach
            </div>
        </div>

        <div class="flex items-center justify-between rounded-[22px] bg-surface p-5 ring-1 ring-hairline">
            <span class="text-[17px] font-semibold">Nombre de Pass</span>
            <div class="flex items-center gap-4">
                <button type="button" wire:click="decrement" class="grid size-11 place-items-center rounded-full ring-1 ring-hairline hover:bg-canvas disabled:opacity-40" @disabled($quantity <= 1) aria-label="Retirer un Pass">−</button>
                <span class="w-6 text-center text-[19px] font-semibold tabular-nums">{{ $quantity }}</span>
                <button type="button" wire:click="increment" class="grid size-11 place-items-center rounded-full ring-1 ring-hairline hover:bg-canvas" aria-label="Ajouter un Pass">+</button>
            </div>
        </div>

        @if ($gift)
            <div class="rounded-[22px] bg-brand-soft p-5">
                <x-ui.input label="Numéro du bénéficiaire" name="beneficiaryPhone" type="tel" wire:model="beneficiaryPhone" placeholder="+33 6 12 34 56 78 ou 07 00 00 00 00"
                            hint="Il recevra son Pass par SMS et WhatsApp." />
            </div>
        @endif

        @error('order') <x-ui.alert tone="danger">{{ $message }}</x-ui.alert> @enderror
    </div>

    {{-- Barre de paiement fixe, comme dans l'app --}}
    <div class="sticky bottom-20 z-20 mt-8 rounded-[22px] bg-surface/90 p-4 shadow-[var(--shadow-float)] ring-1 ring-hairline backdrop-blur-xl sm:bottom-4">
        <div class="flex items-center justify-between text-[15px]">
            <span class="text-ink-secondary">Total (frais de paiement {{ rtrim(rtrim($event->fee_rate, '0'), '.') }} % inclus)</span>
            <span class="text-[19px] font-semibold tabular-nums">{{ $quote ? number_format($quote->total(), 0, ',', ' ').' FCFA' : '—' }}</span>
        </div>
        <label class="mt-3 flex items-start gap-2.5 text-[13px] text-ink-secondary">
            <input type="checkbox" wire:model="acceptTerms" class="mt-0.5 size-4 shrink-0 rounded accent-[var(--color-brand)]">
            <span>J'accepte les <a href="{{ route('legal.terms') }}" target="_blank" class="font-medium text-brand-text underline-offset-2 hover:underline">conditions générales de vente</a>
                et la <a href="{{ route('legal.privacy') }}" target="_blank" class="font-medium text-brand-text underline-offset-2 hover:underline">politique de confidentialité</a>.</span>
        </label>
        @error('acceptTerms')<p class="mt-1 text-[13px] text-danger">{{ $message }}</p>@enderror
        <div class="mt-3 grid gap-2 {{ $gift ? '' : 'sm:grid-cols-2' }}">
            <x-ui.button size="lg" type="button" wire:click="pay" :disabled="! $quote" wire:loading.attr="disabled">{{ $gift ? 'Payer et offrir' : 'Payer' }}</x-ui.button>
            @unless ($gift)
                <x-ui.button size="lg" variant="secondary" type="button" wire:click="$set('gift', true)">Offrir ce Pass</x-ui.button>
            @else
                <button type="button" class="text-[14px] font-medium text-ink-secondary hover:text-ink" wire:click="$set('gift', false)">Annuler le cadeau</button>
            @endunless
        </div>
    </div>
</div>
