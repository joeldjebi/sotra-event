<div>
    <div class="mb-6 w-full sm:w-80">
        <x-ui.select label="Événement" name="eventId" wire:model.live="eventId" :options="$eventOptions" placeholder="Choisir un événement" />
    </div>

    @if (! $event)
        <x-ui.card padding="p-0"><x-ui.empty-state icon="map-pin" title="Aucun événement à venir" description="Les zones de dépose se préparent dès que votre événement est accepté." /></x-ui.card>
    @else
        <div class="grid gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]">
            <x-ui.card padding="p-0">
                <div class="flex flex-wrap items-center justify-between gap-3 px-6 pt-6 pb-4">
                    <div>
                        <h2 class="text-[17px] font-semibold tracking-tight">Zones de dépose et de reprise</h2>
                        <p class="text-[13px] text-ink-secondary">Un bus qui entre dans le périmètre de dépose est compté « arrivé ».</p>
                    </div>
                    <x-ui.button type="button" variant="secondary" wire:click="createZone" icon="map-pin">Ajouter</x-ui.button>
                </div>
                <ul class="ui-list border-t border-separator">
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

            <x-ui.map :data="$map" label="Carte des zones de dépose et de reprise" :height="380" />
        </div>
    @endif

    @include('partials.drop-zone-form')
</div>
