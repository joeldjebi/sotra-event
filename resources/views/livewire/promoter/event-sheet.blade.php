<div>
    <div class="mb-6 w-full sm:w-80">
        <x-ui.select label="Événement" name="eventId" wire:model.live="eventId" :options="$eventOptions" placeholder="Choisir un événement" />
    </div>

    @if (! $event)
        <x-ui.card padding="p-0"><x-ui.empty-state icon="calendar" title="Aucun événement" description="Vos événements proposés par ACCES EVENT apparaîtront ici." /></x-ui.card>
    @else
        <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]">
            <x-ui.card>
                <div class="flex items-start justify-between gap-3">
                    <h2 class="text-[19px] font-semibold tracking-tight">{{ $event->name }}</h2>
                    <x-ui.badge :tone="$event->status->tone()">{{ $event->status->label() }}</x-ui.badge>
                </div>
                <dl class="mt-4 grid grid-cols-2 gap-4 text-[14px]">
                    <div><dt class="text-ink-secondary">Lieu</dt><dd>{{ $event->venue->name }}</dd></div>
                    <div><dt class="text-ink-secondary">Date</dt><dd>{{ ucfirst($event->starts_at->translatedFormat('l j F Y')) }}</dd></div>
                    <div><dt class="text-ink-secondary">Ouverture des portes</dt><dd>{{ $event->doors_open_at?->format('H\hi') ?? '—' }}</dd></div>
                    <div><dt class="text-ink-secondary">Début · fin</dt><dd>{{ $event->starts_at->format('H\hi') }} · {{ $event->ends_at->format('H\hi') }}</dd></div>
                    <div><dt class="text-ink-secondary">Transporteur</dt><dd>{{ $event->company->name }}</dd></div>
                    <div><dt class="text-ink-secondary">Offres</dt><dd>{{ $event->offers->map(fn ($offer) => $offer->offer->label())->join(', ') ?: '—' }}</dd></div>
                </dl>
                @if ($event->description)
                    <p class="mt-4 text-[14px] text-ink-secondary">{{ $event->description }}</p>
                @endif
                <p class="mt-4 text-[12px] text-ink-tertiary">Les informations de l'événement sont gérées par ACCES EVENT. Les zones de dépose sont modifiables dans « Zones de dépose ».</p>
            </x-ui.card>

            <x-ui.table>
                <x-slot:head>
                    <th>Point d'embarquement</th>
                    <th>Zone</th>
                    <th>Départ aller</th>
                    <th>Départ retour</th>
                </x-slot:head>
                @forelse ($event->boardingPoints as $point)
                    <tr wire:key="point-{{ $point->id }}">
                        <td><p class="font-medium">{{ $point->commune }}</p><p class="text-[12px] text-ink-secondary">{{ $point->place }}</p></td>
                        <td>Zone {{ $point->zone }} · {{ number_format((float) $point->distance_km, 1, ',', ' ') }} km</td>
                        <td class="tabular-nums">{{ $point->departure_at->format('H\hi') }}</td>
                        <td class="tabular-nums">{{ $point->return_departure_at->format('H\hi') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4"><x-ui.empty-state icon="map-pin" title="Aucun point" description="Les points d'embarquement sont définis par ACCES EVENT." /></td></tr>
                @endforelse
            </x-ui.table>
        </div>
    @endif
</div>
