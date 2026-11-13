<div>
    <x-ui.card padding="p-0">
        <div class="flex flex-wrap items-center justify-between gap-3 px-6 pt-6 pb-4">
            <div>
                <h2 class="text-[19px] font-semibold tracking-tight">Points d'embarquement</h2>
                <p class="text-[13px] text-ink-secondary">Zone calculée selon la distance au lieu. Les bus sont répartis selon les ventes : pas de quota par point.</p>
            </div>
            @unless ($locked)
                <x-ui.button type="button" wire:click="create" icon="map-pin">Ajouter un point</x-ui.button>
            @endunless
        </div>

        @if ($locked)
            <div class="px-6 pb-4"><x-ui.alert tone="info">Points verrouillés : l'événement a été proposé à la compagnie.</x-ui.alert></div>
        @endif

        <div class="overflow-x-auto">
            <table class="w-full text-left text-[14px]">
                <thead class="border-y border-hairline bg-canvas/60 text-[12px] text-ink-secondary">
                    <tr class="[&>th]:px-5 [&>th]:py-3 [&>th]:font-medium [&>th]:whitespace-nowrap">
                        <th>Commune</th><th>Lieu de montée</th><th>Départ aller</th><th>Départ retour</th><th class="text-right">Distance</th><th>Zone</th><th></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-hairline [&_td]:px-5 [&_td]:py-3.5">
                    @forelse ($points as $point)
                        <tr wire:key="point-{{ $point->id }}">
                            <td class="font-medium">{{ $point->commune }}</td>
                            <td>{{ $point->place }}</td>
                            <td class="whitespace-nowrap tabular-nums">
                                {{ $point->departure_at->format('H:i') }}
                                <p class="text-[12px] text-ink-tertiary">convocation {{ $point->boardingCallAt()->format('H:i') }}</p>
                            </td>
                            <td class="whitespace-nowrap tabular-nums">
                                {{ $point->return_departure_at->format('H:i') }}
                                @if (! $point->return_departure_at->isSameDay($point->departure_at)) <span class="text-[12px] text-ink-tertiary">J+1</span> @endif
                            </td>
                            <td class="text-right tabular-nums whitespace-nowrap">{{ number_format((float) $point->distance_km, 1, ',', ' ') }} km
                                <p class="text-[12px] text-ink-tertiary">≈ {{ $point->travel_minutes }} min</p></td>
                            <td>
                                <x-ui.badge :tone="$point->zone_forced ? 'warning' : 'brand'" :title="$point->zone_justification">Zone {{ $point->zone }}{{ $point->zone_forced ? ' · forcée' : '' }}</x-ui.badge>
                            </td>
                            <td class="text-right whitespace-nowrap">
                                @unless ($locked)
                                    <x-ui.button variant="ghost" size="sm" type="button" wire:click="delete({{ $point->id }})" wire:confirm="Supprimer ce point d'embarquement ?">Supprimer</x-ui.button>
                                    <x-ui.button variant="secondary" size="sm" type="button" wire:click="edit({{ $point->id }})">Modifier</x-ui.button>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-ui.empty-state icon="map-pin" title="Aucun point d'embarquement" description="Ajoutez les gares et carrefours où les spectateurs monteront dans les bus." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.card>

    <x-ui.sheet model="showForm" :title="$editingId ? 'Modifier le point' : 'Nouveau point d\'embarquement'" subtitle="La distance au lieu est calculée à l'enregistrement.">
        <form id="point-form" wire:submit="save" class="space-y-4">
            <x-ui.select label="Commune" name="commune_code" wire:model="commune_code" :options="$communes" placeholder="Choisir" />
            <x-ui.input label="Lieu de montée" name="place" wire:model="place" placeholder="Gare Siporex" />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input label="Latitude" name="latitude" wire:model="latitude" inputmode="decimal" />
                <x-ui.input label="Longitude" name="longitude" wire:model="longitude" inputmode="decimal" />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input label="Départ aller" name="departure_time" type="time" wire:model="departure_time" :hint="'Convocation des spectateurs '.$leadMinutes.' min avant.'" />
                <x-ui.input label="Départ retour" name="return_time" type="time" wire:model="return_time" hint="Le lendemain si plus tôt que l'aller." />
            </div>
            <x-ui.input label="Durée de trajet (min)" name="travel_minutes" type="number" wire:model="travel_minutes" hint="Laissez vide pour une estimation automatique." />

            <x-ui.switch label="Forcer la zone tarifaire" description="Par défaut la zone suit la distance (RG-06)." wire:model.live="zone_forced" />
            @if ($zone_forced)
                <x-ui.select label="Zone" name="forced_zone" wire:model="forced_zone" :options="$zoneOptions" />
                <x-ui.input label="Justification" name="zone_justification" wire:model="zone_justification" placeholder="Accès par l'autoroute du Nord" />
            @endif
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" type="button" x-on:click="open = false">Annuler</x-ui.button>
            <x-ui.button form="point-form">Enregistrer</x-ui.button>
        </x-slot:footer>
    </x-ui.sheet>
</div>
