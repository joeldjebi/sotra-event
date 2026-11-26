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
                            <x-ui.button variant="secondary" size="sm" type="button" wire:click="show({{ $event->id }})">{{ $this->canManage() ? 'Voir le détail et négocier' : 'Voir le détail' }}</x-ui.button>
                            @if ($this->canManage() && $event->status === \App\Enums\EventStatus::Proposed)
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

    <x-ui.sheet model="showDetails" :title="$viewing?->name ?? 'Événement'" :subtitle="$viewing ? $viewing->venue->name.' · '.$viewing->starts_at->translatedFormat('l j F Y, H\hi') : null" width="max-w-4xl">
        @if ($viewing)
            @php($tile = 'rounded-[var(--radius-control)] bg-surface p-3.5 shadow-[var(--shadow-card)] ring-1 ring-hairline')
            @php($box = 'overflow-x-auto rounded-[var(--radius-card)] bg-surface shadow-[var(--shadow-card)] ring-1 ring-hairline')
            @php($fcfa = fn (?int $amount) => $amount === null ? '—' : number_format($amount, 0, ',', ' '))
            <div class="space-y-8">
                <div class="flex flex-wrap items-center gap-2">
                    <x-ui.badge :tone="$viewing->status->tone()">{{ $viewing->status->label() }}</x-ui.badge>
                    @if ($viewing->proposed_at)<span class="text-[13px] text-ink-secondary">Proposé le {{ $viewing->proposed_at->translatedFormat('j M Y à H\hi') }}</span>@endif
                </div>

                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div class="{{ $tile }}"><p class="text-caption font-semibold text-ink-secondary">Pass estimés</p><p class="mt-1 font-rounded text-[22px] leading-none font-semibold tabular-nums">{{ $viewing->estimated_passes ? number_format($viewing->estimated_passes, 0, ',', ' ') : '—' }}</p></div>
                    <div class="{{ $tile }}"><p class="text-caption font-semibold text-ink-secondary">Points d'embarquement</p><p class="mt-1 font-rounded text-[22px] leading-none font-semibold">{{ $viewing->boardingPoints->count() }}</p></div>
                    <div class="{{ $tile }}"><p class="text-caption font-semibold text-ink-secondary">Part compagnie</p><p class="mt-1 font-rounded text-[22px] leading-none font-semibold">{{ 100 - (float) $viewing->commission_rate }} %</p></div>
                    <div class="{{ $tile }}"><p class="text-caption font-semibold text-ink-secondary">Offres</p><p class="mt-1 truncate text-[15px] font-semibold">{{ collect($viewing->activeOffers())->map->label()->join(', ') ?: '—' }}</p></div>
                </div>

                <section>
                    <x-ui.section-title title="Événement" />
                    <div class="grid gap-4 rounded-[var(--radius-card)] bg-surface p-4 shadow-[var(--shadow-card)] ring-1 ring-hairline sm:grid-cols-[1fr_auto]">
                        <dl class="grid gap-x-6 gap-y-3 text-[14px] sm:grid-cols-2">
                            <div><dt class="text-ink-tertiary">Début</dt><dd class="font-medium">{{ $viewing->starts_at->translatedFormat('l j F Y, H\hi') }}</dd></div>
                            <div><dt class="text-ink-tertiary">Fin prévue</dt><dd class="font-medium">{{ $viewing->ends_at->translatedFormat('l j F Y, H\hi') }}</dd></div>
                            <div><dt class="text-ink-tertiary">Ouverture des portes</dt><dd class="font-medium">{{ $viewing->doors_open_at?->translatedFormat('H\hi') ?? '—' }}</dd></div>
                            <div><dt class="text-ink-tertiary">Lieu</dt><dd class="font-medium">{{ $viewing->venue->name }}</dd><dd class="text-[13px] text-ink-secondary">{{ collect([$viewing->venue->address, $viewing->venue->city])->filter()->join(', ') }}</dd></div>
                            <div><dt class="text-ink-tertiary">Canaux de vente</dt><dd class="font-medium">{{ collect($viewing->channels)->map(fn ($channel) => \App\Enums\SalesChannel::tryFrom($channel)?->label())->filter()->join(', ') ?: 'À définir' }}</dd></div>
                            @if ($viewing->description)
                                <div class="sm:col-span-2"><dt class="text-ink-tertiary">Description</dt><dd class="whitespace-pre-line">{{ $viewing->description }}</dd></div>
                            @endif
                        </dl>
                        @if ($viewing->visualUrl())
                            <img src="{{ $viewing->visualUrl() }}" alt="Affiche de {{ $viewing->name }}" class="h-40 w-auto rounded-[var(--radius-control)] object-cover ring-1 ring-hairline">
                        @endif
                    </div>
                </section>

                <section>
                    <x-ui.section-title title="Contacts" />
                    <dl class="grid gap-3 rounded-[var(--radius-card)] bg-surface p-4 text-[14px] shadow-[var(--shadow-card)] ring-1 ring-hairline sm:grid-cols-2">
                        <div><dt class="text-ink-tertiary">Promoteur</dt><dd class="font-medium">{{ $viewing->promoter->name }}</dd><dd class="text-[13px] text-ink-secondary">{{ collect([$viewing->promoter->phone, $viewing->promoter->email])->filter()->join(' · ') }}</dd></div>
                        <div><dt class="text-ink-tertiary">Chef de projet transport</dt>
                            @if ($viewing->transportLead)
                                <dd class="font-medium">{{ $viewing->transportLead->name }}</dd><dd class="text-[13px] text-ink-secondary">{{ collect([$viewing->transportLead->phone, $viewing->transportLead->email])->filter()->join(' · ') }}</dd>
                            @else
                                <dd class="text-ink-secondary">Non désigné</dd>
                            @endif
                        </div>
                    </dl>
                </section>

                <section>
                    <x-ui.section-title title="Offres proposées" description="Premium et Gold circulent dans des bus dédiés. Le nombre de bus et de places se fixe dans le plan de transport." />
                    <div class="grid gap-3 sm:grid-cols-3">
                        @foreach ($viewing->offers->sortBy(fn ($offer) => array_search($offer->offer, \App\Enums\Offer::cases(), true)) as $eventOffer)
                            <div wire:key="vo-card-{{ $eventOffer->id }}" class="{{ $tile }}">
                                <p class="font-semibold">{{ $eventOffer->offer->label() }}</p>
                                <p class="text-[13px] text-ink-secondary">{{ $eventOffer->offer->tagline() }}</p>
                                <p class="mt-2 text-[13px]">{{ $eventOffer->bus_count && $eventOffer->seats_per_bus ? $eventOffer->bus_count.' bus × '.$eventOffer->seats_per_bus.' places' : 'Bus et places à définir' }}</p>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section>
                    <x-ui.section-title title="Points d'embarquement" description="Zone tarifaire selon la distance au lieu : 1 (< 15 km), 2 (15 à 25 km), 3 (> 25 km)." />
                    <div class="{{ $box }}">
                        <table class="ui-table ui-table--compact">
                            <thead><tr><th>Commune</th><th>Lieu</th><th>Distance</th><th>Zone</th><th>Convocation</th><th>Départ aller</th><th>Départ retour</th></tr></thead>
                            <tbody>
                                @foreach ($viewing->boardingPoints as $point)
                                    <tr wire:key="vp-{{ $point->id }}" @class(['opacity-55' => $point->cancelled_at])>
                                        <td class="font-medium">{{ $point->commune }}@if ($point->cancelled_at) <x-ui.badge tone="danger" class="ml-1">Annulé</x-ui.badge>@endif</td>
                                        <td>{{ $point->place }}</td>
                                        <td class="tabular-nums whitespace-nowrap">{{ number_format((float) $point->distance_km, 1, ',', ' ') }} km</td>
                                        <td>Zone {{ $point->zone }}@if ($point->zone_forced)<span class="block text-[12px] text-ink-tertiary" title="{{ $point->zone_justification }}">zone forcée</span>@endif</td>
                                        <td class="tabular-nums">{{ $point->boardingCallAt()->format('H:i') }}</td>
                                        <td class="tabular-nums">{{ $point->departure_at->format('H:i') }}@if ($point->travel_minutes)<span class="block text-[12px] text-ink-tertiary">{{ $point->travel_minutes }} min de trajet</span>@endif</td>
                                        <td class="tabular-nums">{{ $point->return_departure_at->format('H:i') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>

                <section>
                    <x-ui.section-title title="Grille tarifaire" description="Prix en FCFA payés par le spectateur, hors frais de paiement ({{ (float) $viewing->fee_rate }} % ajoutés au client)." />
                    <div class="space-y-4">
                        @forelse ($grid as $passType => $table)
                            <div wire:key="vg-{{ $passType }}" class="{{ $box }}">
                                <table class="ui-table ui-table--compact">
                                    <thead><tr><th>{{ $table['label'] }}</th>
                                        @foreach ($viewing->activeOffers() as $offer)<th wire:key="vo-{{ $passType }}-{{ $offer->value }}">{{ $offer->label() }}</th>@endforeach</tr></thead>
                                    <tbody>
                                        @foreach ($table['rows'] as $zone => $prices)
                                            <tr wire:key="vz-{{ $passType }}-{{ $zone }}"><td class="font-medium">Zone {{ $zone }}</td>
                                                @foreach ($viewing->activeOffers() as $offer)
                                                    <td wire:key="vc-{{ $passType }}-{{ $zone }}-{{ $offer->value }}" class="tabular-nums">{{ $fcfa($prices[$offer->value] ?? null) }}</td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @empty
                            <p class="px-1 text-[14px] text-ink-secondary">Aucun prix défini.</p>
                        @endforelse
                    </div>
                </section>

                <section>
                    <x-ui.section-title title="Zones de dépose et de reprise" description="Où les bus déposent les spectateurs à l'aller et les reprennent au retour." />
                    <div class="{{ $box }}">
                        <table class="ui-table ui-table--compact">
                            <thead><tr><th>Zone</th><th>Usage</th><th>Heure prévue</th><th>Rayon</th></tr></thead>
                            <tbody>
                                @forelse ($viewing->dropZones as $dropZone)
                                    <tr wire:key="vd-{{ $dropZone->id }}"><td class="font-medium">{{ $dropZone->name }}</td><td>{{ $dropZone->usage->label() }}</td><td class="tabular-nums">{{ $dropZone->scheduled_at?->format('H:i') ?? '—' }}</td><td class="tabular-nums">{{ $dropZone->radius_m }} m</td></tr>
                                @empty
                                    <tr><td colspan="4" class="text-ink-secondary">Pas encore définies par ACCES EVENT.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                <section>
                    <x-ui.section-title title="Conditions financières" />
                    @php($sample = $grid[\App\Enums\PassType::RoundTrip->value]['rows'][$viewing->usedZones()[0] ?? 1][\App\Enums\Offer::Standard->value] ?? null)
                    <dl class="grid gap-3 rounded-[var(--radius-card)] bg-surface p-4 text-[14px] shadow-[var(--shadow-card)] ring-1 ring-hairline sm:grid-cols-3">
                        <div><dt class="text-ink-tertiary">Part de la compagnie</dt><dd class="font-semibold">{{ 100 - (float) $viewing->commission_rate }} % du prix du Pass</dd></div>
                        <div><dt class="text-ink-tertiary">Commission ACCES EVENT</dt><dd class="font-semibold">{{ (float) $viewing->commission_rate }} %</dd></div>
                        <div><dt class="text-ink-tertiary">Frais de paiement</dt><dd class="font-semibold">{{ (float) $viewing->fee_rate }} %, payés par le spectateur</dd></div>
                        @if ($sample)
                            <div class="sm:col-span-3 text-[13px] text-ink-secondary">Exemple : un aller-retour Standard en zone {{ $viewing->usedZones()[0] }} à {{ $fcfa($sample) }} FCFA rapporte {{ $fcfa((int) round($sample * (100 - (float) $viewing->commission_rate) / 100)) }} FCFA à la compagnie.</div>
                        @endif
                    </dl>
                </section>

                <section>
                    <x-ui.section-title title="Échanges avec ACCES EVENT" />
                    <x-ui.negotiation-thread :messages="$viewing->negotiations" viewer-side="company" />

                    @if ($this->canManage() && in_array($viewing->status, [\App\Enums\EventStatus::Proposed, \App\Enums\EventStatus::Negotiating], true))
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
        @if ($viewing && $this->canManage() && $viewing->status === \App\Enums\EventStatus::Proposed)
            <x-slot:footer>
                <x-ui.button variant="dark" type="button" wire:click="accept({{ $viewing->id }})" wire:confirm="Accepter « {{ $viewing->name }} » aux conditions proposées ?">Accepter l'événement</x-ui.button>
            </x-slot:footer>
        @endif
    </x-ui.sheet>
</div>
