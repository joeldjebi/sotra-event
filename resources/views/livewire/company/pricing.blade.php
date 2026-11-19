<div>
    @php($fcfa = fn (?int $value): string => $value === null ? '—' : number_format($value, 0, ',', ' '))

    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div class="w-full sm:w-80">
            <x-ui.select label="Événement" name="eventId" wire:model.live="eventId" :options="$eventOptions" placeholder="Choisir un événement" />
        </div>
        @if ($event)
            <x-ui.badge :tone="$event->status->tone()">{{ $event->status->label() }}</x-ui.badge>
        @endif
    </div>

    @if (! $event)
        <x-ui.card padding="p-0"><x-ui.empty-state icon="tag" title="Aucun événement" description="Les offres et la grille de chaque événement proposé à votre compagnie apparaîtront ici." /></x-ui.card>
    @else
        <x-ui.alert tone="info" class="mb-6">
            La grille est fixée dans la console ACCES EVENT. Pour la discuter, passez par
            @can(\App\Enums\Permission::ProposedEventsView->value)
                <a href="{{ route('company.proposed-events') }}" class="font-medium underline">Événements proposés</a>
            @else
                Événements proposés
            @endcan
            tant que l'événement n'est pas accepté.
        </x-ui.alert>

        <section class="space-y-3">
            <h2 class="text-title-3">Offres</h2>
            <div class="grid gap-4 sm:grid-cols-3">
                @forelse ($event->offers as $offer)
                    <x-ui.card wire:key="pricing-offer-{{ $offer->id }}">
                        <p class="text-[13px] font-medium text-ink-secondary">{{ $offer->offer->label() }}</p>
                        <p class="mt-1 font-rounded text-[28px] font-semibold tabular-nums">{{ $offer->capacity() ? number_format($offer->capacity(), 0, ',', ' ') : '—' }}</p>
                        <p class="text-[13px] text-ink-secondary">
                            {{ $offer->bus_count ? $offer->bus_count.' bus' : 'bus à définir' }} · {{ $offer->seats_per_bus ? $offer->seats_per_bus.' places par bus' : 'places à définir' }}
                        </p>
                    </x-ui.card>
                @empty
                    <x-ui.card class="sm:col-span-3"><p class="text-[14px] text-ink-secondary">Aucune offre ouverte pour cet événement.</p></x-ui.card>
                @endforelse
            </div>
        </section>

        <section class="mt-8 space-y-3">
            <div>
                <h2 class="text-title-3">Grille tarifaire</h2>
                <p class="text-[13px] text-ink-secondary">Prix d'un Pass en FCFA, hors frais de service, par zone, offre et type de trajet.</p>
            </div>
            <x-ui.table>
                <x-slot:head>
                    <th>Zone</th>
                    @foreach ($offers as $offer)
                        @foreach ($passTypes as $type)
                            <th wire:key="head-{{ $offer->value }}-{{ $type->value }}" class="text-right">{{ $offer->label() }}<br><span class="font-normal">{{ $type->label() }}</span></th>
                        @endforeach
                    @endforeach
                </x-slot:head>
                @forelse ($zones as $zone)
                    <tr wire:key="zone-{{ $zone }}">
                        <td class="font-medium">Zone {{ $zone }}</td>
                        @foreach ($offers as $offer)
                            @foreach ($passTypes as $type)
                                <td wire:key="price-{{ $zone }}-{{ $offer->value }}-{{ $type->value }}" class="text-right tabular-nums">{{ $fcfa($grid[$zone][$offer->value][$type->value] ?? null) }}</td>
                            @endforeach
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ 1 + count($offers) * count($passTypes) }}"><x-ui.empty-state icon="tag" title="Grille non renseignée" description="ACCES EVENT n'a pas encore saisi les prix de cet événement." /></td></tr>
                @endforelse
            </x-ui.table>
        </section>

        <section class="mt-8 space-y-3">
            <h2 class="text-title-3">Points d'embarquement</h2>
            <x-ui.table>
                <x-slot:head><th>Commune</th><th>Lieu</th><th>Zone</th><th class="text-right">Distance</th><th>Départ aller</th><th>Départ retour</th></x-slot:head>
                @forelse ($event->boardingPoints as $point)
                    <tr wire:key="pricing-point-{{ $point->id }}" @class(['opacity-55' => $point->cancelled_at])>
                        <td class="font-medium">{{ $point->commune }}</td>
                        <td class="text-ink-secondary">{{ $point->place }}</td>
                        <td>Zone {{ $point->zone }}</td>
                        <td class="text-right tabular-nums">{{ $point->distance_km !== null ? number_format((float) $point->distance_km, 1, ',', ' ').' km' : '—' }}</td>
                        <td class="tabular-nums">{{ $point->departure_at?->format('d/m H\hi') ?? '—' }}</td>
                        <td class="tabular-nums">{{ $point->return_departure_at?->format('d/m H\hi') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-ui.empty-state icon="map-pin" title="Aucun point" description="Les points d'embarquement seront ajoutés par ACCES EVENT." /></td></tr>
                @endforelse
            </x-ui.table>
        </section>
    @endif
</div>
