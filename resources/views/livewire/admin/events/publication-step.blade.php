<div class="space-y-6">
    <x-ui.card>
        <form wire:submit="saveChannels" class="space-y-8">
            <section>
                <x-ui.section-title title="Canaux de vente" />
                <div class="flex flex-wrap gap-2">
                    @foreach ($availableChannels as $channel)
                        <label wire:key="channel-{{ $channel->value }}" @class([
                            'inline-flex items-center gap-2 rounded-full px-4 py-2 text-[14px] font-medium ring-1 transition',
                            'cursor-not-allowed bg-fill text-ink-tertiary ring-transparent' => ! $channel->isAvailable(),
                            'cursor-pointer bg-success-soft text-success ring-success/30' => $channel->isAvailable() && in_array($channel->value, $channels, true),
                            'cursor-pointer bg-surface ring-hairline hover:bg-canvas' => $channel->isAvailable() && ! in_array($channel->value, $channels, true),
                        ])>
                            <input type="checkbox" class="sr-only" value="{{ $channel->value }}" wire:model.live="channels" @disabled(! $channel->isAvailable())>
                            {{ $channel->label() }}
                            @unless ($channel->isAvailable()) <span class="text-[11px]">· V2</span> @endunless
                        </label>
                    @endforeach
                </div>
            </section>

            <section class="space-y-4">
                <x-ui.section-title title="Présentation" />
                <div class="flex flex-wrap items-center gap-5">
                    <div class="grid size-28 shrink-0 place-items-center overflow-hidden rounded-2xl bg-fill">
                        @if ($visual)
                            <img src="{{ $visual->temporaryUrl() }}" alt="" class="size-full object-cover">
                        @elseif ($event->visualUrl())
                            <img src="{{ $event->visualUrl() }}" alt="" class="size-full object-cover">
                        @else
                            <x-ui.icon name="sparkles" class="size-7 text-ink-tertiary" />
                        @endif
                    </div>
                    <div class="space-y-2">
                        <label class="inline-flex cursor-pointer">
                            <span class="inline-flex h-9 items-center rounded-full bg-surface px-4 text-[13px] font-medium ring-1 ring-hairline hover:bg-canvas">Choisir un visuel</span>
                            <input type="file" accept="image/*" class="sr-only" wire:model="visual">
                        </label>
                        <p class="text-[13px] text-ink-tertiary">JPG ou PNG, 4 Mo maximum.</p>
                        @error('visual') <p class="text-[13px] text-danger">{{ $message }}</p> @enderror
                    </div>
                </div>
                <x-ui.switch label="Mettre en avant" description="Affiché en tête de l'accueil du site et de l'application." wire:model="featured" />
            </section>

            <div class="flex justify-end"><x-ui.button>Enregistrer</x-ui.button></div>
        </form>
    </x-ui.card>

    <x-ui.card padding="p-0">
        <div class="flex flex-wrap items-center justify-between gap-3 px-6 pt-6 pb-4">
            <div>
                <h2 class="text-[19px] font-semibold tracking-tight">Zones de dépose et de reprise</h2>
                <p class="text-[13px] text-ink-secondary">Un bus qui entre dans le périmètre de dépose est compté « arrivé ».</p>
            </div>
            <x-ui.button type="button" variant="secondary" wire:click="createZone" icon="map-pin">Ajouter une zone</x-ui.button>
        </div>
        <ul class="divide-y divide-hairline border-t border-hairline">
            @forelse ($zones as $zone)
                <li wire:key="zone-{{ $zone->id }}" class="flex flex-wrap items-center justify-between gap-3 px-6 py-3.5">
                    <div>
                        <p class="font-medium">{{ $zone->name }}</p>
                        <p class="text-[13px] text-ink-secondary">{{ $zone->usage->label() }}@if ($zone->scheduled_at) · {{ $zone->scheduled_at->format('H\hi') }}@endif · rayon {{ $zone->radius_m }} m</p>
                    </div>
                    <div class="whitespace-nowrap">
                        <x-ui.button variant="ghost" size="sm" type="button" wire:click="deleteZone({{ $zone->id }})" wire:confirm="Supprimer cette zone ?">Supprimer</x-ui.button>
                        <x-ui.button variant="secondary" size="sm" type="button" wire:click="editZone({{ $zone->id }})">Modifier</x-ui.button>
                    </div>
                </li>
            @empty
                <li><x-ui.empty-state icon="map-pin" title="Aucune zone" description="Ajoutez au moins une zone de dépose aller et une zone de reprise retour." /></li>
            @endforelse
        </ul>
    </x-ui.card>

    <x-ui.sheet model="showZoneForm" :title="$editingZoneId ? 'Modifier la zone' : 'Nouvelle zone'">
        <form id="zone-form" wire:submit="saveZone" class="space-y-4">
            <x-ui.input label="Nom" name="zone_name" wire:model="zone_name" placeholder="Parking P3 · porte Nord" />
            <x-ui.select label="Usage" name="zone_usage" wire:model="zone_usage" :options="$usages" />
            <x-ui.input label="Heure (reprise retour)" name="zone_time" type="time" wire:model="zone_time" />
            <div class="grid gap-4 sm:grid-cols-3">
                <x-ui.input label="Latitude" name="zone_latitude" wire:model="zone_latitude" inputmode="decimal" />
                <x-ui.input label="Longitude" name="zone_longitude" wire:model="zone_longitude" inputmode="decimal" />
                <x-ui.input label="Rayon (m)" name="zone_radius" type="number" wire:model="zone_radius" />
            </div>
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" type="button" x-on:click="open = false">Annuler</x-ui.button>
            <x-ui.button form="zone-form">Enregistrer</x-ui.button>
        </x-slot:footer>
    </x-ui.sheet>
</div>
