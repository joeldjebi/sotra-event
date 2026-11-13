<div class="space-y-8">
    <section>
        <h2 class="mb-3 px-1 text-title-3">À valider</h2>
        <x-ui.card padding="p-0">
            <ul class="ui-list">
                @forelse ($pending as $event)
                    <li wire:key="pending-{{ $event->id }}" class="flex flex-wrap items-center justify-between gap-4 px-6 py-4">
                        <div class="min-w-0">
                            <p class="font-semibold">{{ $event->name }}
                                @if ($event->status === \App\Enums\EventStatus::Negotiating) <x-ui.badge tone="warning" class="ml-1">En négociation</x-ui.badge> @endif
                            </p>
                            <p class="text-[13px] text-ink-secondary">
                                {{ $event->venue->name }} · {{ $event->starts_at->translatedFormat('j M Y') }}
                                · {{ $event->estimated_passes ? number_format($event->estimated_passes, 0, ',', ' ').' Pass estimés' : 'volume non estimé' }}
                                · {{ $event->boarding_points_count }} points
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <x-ui.button variant="secondary" size="sm" type="button" wire:click="show({{ $event->id }})">Négocier</x-ui.button>
                            @if ($event->status === \App\Enums\EventStatus::Proposed)
                                <x-ui.button variant="dark" size="sm" type="button" wire:click="accept({{ $event->id }})" wire:confirm="Accepter « {{ $event->name }} » aux conditions proposées ?">Accepter</x-ui.button>
                            @endif
                        </div>
                    </li>
                @empty
                    <li><x-ui.empty-state icon="calendar" title="Aucun événement à valider" description="Les nouvelles propositions d'ACCES EVENT apparaîtront ici." /></li>
                @endforelse
            </ul>
        </x-ui.card>
    </section>

    @if ($others->isNotEmpty())
        <section>
            <h2 class="mb-3 px-1 text-title-3">Suivis</h2>
            <x-ui.table>
                <x-slot:head><th>Événement</th><th>Date</th><th>Statut</th><th></th></x-slot:head>
                @foreach ($others as $event)
                    <tr wire:key="other-{{ $event->id }}">
                        <td><p class="font-medium">{{ $event->name }}</p><p class="text-[13px] text-ink-secondary">{{ $event->venue->name }}</p></td>
                        <td class="whitespace-nowrap">{{ $event->starts_at->translatedFormat('j M Y, H\hi') }}</td>
                        <td><x-ui.badge :tone="$event->status->tone()">{{ $event->status->label() }}</x-ui.badge></td>
                        <td class="text-right"><x-ui.button variant="secondary" size="sm" type="button" wire:click="show({{ $event->id }})">Détails</x-ui.button></td>
                    </tr>
                @endforeach
            </x-ui.table>
        </section>
    @endif

    <x-ui.sheet model="showDetails" :title="$viewing?->name ?? 'Événement'" :subtitle="$viewing ? $viewing->venue->name.' · '.$viewing->starts_at->translatedFormat('l j F Y, H\hi') : null" width="max-w-2xl">
        @if ($viewing)
            <div class="space-y-8">
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div class="rounded-[var(--radius-control)] bg-surface p-3.5 shadow-[var(--shadow-card)] ring-1 ring-hairline"><p class="text-caption font-semibold text-ink-secondary">Pass estimés</p><p class="mt-1 font-rounded text-[22px] leading-none font-semibold tabular-nums">{{ $viewing->estimated_passes ? number_format($viewing->estimated_passes, 0, ',', ' ') : '—' }}</p></div>
                    <div class="rounded-[var(--radius-control)] bg-surface p-3.5 shadow-[var(--shadow-card)] ring-1 ring-hairline"><p class="text-caption font-semibold text-ink-secondary">Points</p><p class="mt-1 font-rounded text-[22px] leading-none font-semibold">{{ $viewing->boardingPoints->count() }}</p></div>
                    <div class="rounded-[var(--radius-control)] bg-surface p-3.5 shadow-[var(--shadow-card)] ring-1 ring-hairline"><p class="text-caption font-semibold text-ink-secondary">Part compagnie</p><p class="mt-1 font-rounded text-[22px] leading-none font-semibold">{{ 100 - (float) $viewing->commission_rate }} %</p></div>
                    <div class="rounded-[var(--radius-control)] bg-surface p-3.5 shadow-[var(--shadow-card)] ring-1 ring-hairline"><p class="text-caption font-semibold text-ink-secondary">Promoteur</p><p class="mt-1 truncate text-[15px] font-semibold">{{ $viewing->promoter->name }}</p></div>
                </div>

                <section>
                    <x-ui.section-title title="Points d'embarquement" />
                    <div class="overflow-x-auto rounded-[var(--radius-card)] bg-surface shadow-[var(--shadow-card)] ring-1 ring-hairline">
                        <table class="ui-table ui-table--compact">
                            <thead><tr><th>Commune</th><th>Lieu</th><th>Aller</th><th>Retour</th><th>Zone</th></tr></thead>
                            <tbody>
                                @foreach ($viewing->boardingPoints as $point)
                                    <tr wire:key="vp-{{ $point->id }}"><td class="font-medium">{{ $point->commune }}</td><td>{{ $point->place }}</td><td class="tabular-nums">{{ $point->departure_at->format('H:i') }}</td><td class="tabular-nums">{{ $point->return_departure_at->format('H:i') }}</td><td>Zone {{ $point->zone }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>

                <section>
                    <x-ui.section-title title="Grille aller-retour (FCFA, hors frais)" />
                    <div class="overflow-x-auto rounded-[var(--radius-card)] bg-surface shadow-[var(--shadow-card)] ring-1 ring-hairline">
                        <table class="ui-table ui-table--compact">
                            <thead><tr><th>Zone</th>
                                @foreach ($viewing->activeOffers() as $offer)<th wire:key="vo-{{ $offer->value }}">{{ $offer->label() }}</th>@endforeach</tr></thead>
                            <tbody>
                                @foreach ($viewing->usedZones() as $zone)
                                    <tr wire:key="vz-{{ $zone }}"><td class="font-medium">Zone {{ $zone }}</td>
                                        @foreach ($viewing->activeOffers() as $offer)
                                            @php($price = $viewing->prices->first(fn ($p) => $p->zone === $zone && $p->offer === $offer && $p->pass_type === $roundTrip))
                                            <td wire:key="vc-{{ $zone }}-{{ $offer->value }}" class="tabular-nums">{{ $price ? number_format($price->price, 0, ',', ' ') : '—' }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>

                <section>
                    <x-ui.section-title title="Échanges avec ACCES EVENT" />
                    <x-ui.negotiation-thread :messages="$viewing->negotiations" viewer-side="company" />

                    @if (in_array($viewing->status, [\App\Enums\EventStatus::Proposed, \App\Enums\EventStatus::Negotiating], true))
                        <form wire:submit="negotiate" class="mt-5 space-y-3 rounded-[var(--radius-card)] bg-surface p-4 shadow-[var(--shadow-card)] ring-1 ring-hairline">
                            <x-ui.select label="Objet" name="subject" wire:model="subject" :options="$subjects" />
                            <x-ui.textarea label="Votre demande" name="message" wire:model="message" placeholder="Ex. : nous proposons 3 500 FCFA en zone 1 pour couvrir le carburant." />
                            @error('event') <p class="text-[13px] text-danger">{{ $message }}</p> @enderror
                            <x-ui.button variant="secondary" size="sm">Envoyer la demande</x-ui.button>
                        </form>
                    @endif
                </section>
            </div>
        @endif
        @if ($viewing && $viewing->status === \App\Enums\EventStatus::Proposed)
            <x-slot:footer>
                <x-ui.button variant="dark" type="button" wire:click="accept({{ $viewing->id }})" wire:confirm="Accepter « {{ $viewing->name }} » aux conditions proposées ?">Accepter l'événement</x-ui.button>
            </x-slot:footer>
        @endif
    </x-ui.sheet>
</div>
