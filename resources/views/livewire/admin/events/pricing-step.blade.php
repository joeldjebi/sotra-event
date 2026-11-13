<x-ui.card>
    <form wire:submit="save">
        <fieldset @disabled($locked) class="space-y-8">
            <div>
                <h2 class="text-title-3">Grille tarifaire</h2>
                <p class="text-[13px] text-ink-secondary">Fixée avec la compagnie selon la distance · prix en FCFA, hors frais de paiement ({{ rtrim(rtrim($event->fee_rate, '0'), '.') }} % ajoutés au client).</p>
            </div>

            @if ($locked)
                <x-ui.alert tone="info">Grille verrouillée : l'événement a été proposé à la compagnie. Elle redevient modifiable en cas de négociation.</x-ui.alert>
            @endif

            <section>
                <x-ui.section-title title="Offres proposées" description="Premium et Gold circulent dans des bus dédiés (RG-09)." />
                <div class="grid gap-3 sm:grid-cols-3">
                    @foreach ($offers as $offer)
                        <label wire:key="offer-{{ $offer->value }}" @class([
                            'flex cursor-pointer items-start gap-3 rounded-[var(--radius-card)] p-4 transition duration-200 active:scale-[0.99]',
                            'bg-brand-soft ring-2 ring-brand' => in_array($offer->value, $activeOffers, true),
                            'bg-surface shadow-[var(--shadow-card)] ring-1 ring-hairline hover:bg-fill-subtle' => ! in_array($offer->value, $activeOffers, true),
                        ])>
                            <input type="checkbox" value="{{ $offer->value }}" wire:model.live="activeOffers" class="ui-check mt-0.5">
                            <span>
                                <span class="block text-[15px] font-semibold">{{ $offer->label() }}</span>
                                <span class="block text-[13px] text-ink-secondary">{{ $offer->tagline() }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('activeOffers') <p class="mt-2 text-[13px] text-danger">{{ $message }}</p> @enderror
            </section>

            @foreach ($passTypes as $type)
                <section wire:key="type-{{ $type->value }}">
                    <x-ui.section-title :title="$type->label()" :description="$type->value === 'aller_retour' ? 'Obligatoire pour chaque zone utilisée.' : 'Facultatif : laissez vide pour ne pas vendre ce type.'" />
                    <div class="overflow-x-auto rounded-[var(--radius-card)] bg-surface shadow-[var(--shadow-card)] ring-1 ring-hairline">
                        <table class="ui-table ui-table--compact">
                            <thead>
                                <tr>
                                    <th>Zone</th><th>Distance</th>
                                    @foreach ($offers as $offer)
                                        @if (in_array($offer->value, $activeOffers, true))
                                            <th wire:key="h-{{ $type->value }}-{{ $offer->value }}">{{ $offer->label() }}</th>
                                        @endif
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($zones as $zone)
                                    <tr wire:key="row-{{ $type->value }}-{{ $zone }}" class="[&>td]:!align-top">
                                        <td class="font-medium whitespace-nowrap">Zone {{ $zone }}
                                            <p class="text-[12px] font-normal {{ ($usedZones[$zone] ?? 0) > 0 ? 'text-success' : 'text-ink-tertiary' }}">{{ ($usedZones[$zone] ?? 0) > 0 ? ($usedZones[$zone].' point(s)') : 'non utilisée' }}</p>
                                        </td>
                                        <td class="text-ink-secondary whitespace-nowrap">{{ \App\Services\ZoneResolver::zoneLabel($zone) }}</td>
                                        @foreach ($offers as $offer)
                                            @if (in_array($offer->value, $activeOffers, true))
                                                @php($key = "prices.{$type->value}.{$zone}.{$offer->value}")
                                                <td wire:key="cell-{{ $type->value }}-{{ $zone }}-{{ $offer->value }}" class="min-w-36">
                                                    <div class="relative">
                                                        <input type="number" inputmode="numeric" step="100" wire:model="{{ $key }}"
                                                               class="ui-field h-10 pr-14 pl-3" @if ($errors->has($key)) aria-invalid="true" @endif>
                                                        <span class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-[12px] text-ink-tertiary">FCFA</span>
                                                    </div>
                                                    @error($key) <p class="mt-1 text-[12px] text-danger">{{ $message }}</p> @enderror
                                                </td>
                                            @endif
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endforeach
        </fieldset>

        @unless ($locked)
            <div class="mt-8 flex justify-end"><x-ui.button>Enregistrer la grille</x-ui.button></div>
        @endunless
    </form>
</x-ui.card>
