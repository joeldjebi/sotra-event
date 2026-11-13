<div>
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <x-ui.search wire:model.live.debounce.300ms="search" placeholder="Rechercher un lieu" />
        <x-ui.button wire:click="create" type="button" icon="sparkles">Nouveau lieu</x-ui.button>
    </div>

    <x-ui.table>
        <x-slot:head>
            <th>Lieu</th>
            <th>Ville</th>
            <th>Coordonnées GPS</th>
            <th></th>
        </x-slot:head>

        @forelse ($venues as $venue)
            <tr wire:key="venue-{{ $venue->id }}" class="hover:bg-fill-subtle">
                <td>
                    <p class="font-medium">{{ $venue->name }}</p>
                    <p class="text-[13px] text-ink-secondary">{{ $venue->address }}</p>
                </td>
                <td class="text-ink-secondary">{{ $venue->city }}</td>
                <td>
                    <a class="font-mono text-[13px] text-brand-text hover:underline" target="_blank" rel="noopener"
                       href="https://www.openstreetmap.org/?mlat={{ $venue->location->getLatitude() }}&mlon={{ $venue->location->getLongitude() }}#map=16/{{ $venue->location->getLatitude() }}/{{ $venue->location->getLongitude() }}">
                        {{ number_format($venue->location->getLatitude(), 5) }}, {{ number_format($venue->location->getLongitude(), 5) }}
                    </a>
                </td>
                <td class="text-right"><x-ui.button variant="secondary" size="sm" type="button" wire:click="edit({{ $venue->id }})">Modifier</x-ui.button></td>
            </tr>
        @empty
            <tr><td colspan="4"><x-ui.empty-state icon="map-pin" title="Aucun lieu" description="Ajoutez les stades et salles qui accueilleront les événements." /></td></tr>
        @endforelse

        @if ($venues->hasPages())
            <x-slot:footer>{{ $venues->links() }}</x-slot:footer>
        @endif
    </x-ui.table>

    <x-ui.sheet model="showForm" :title="$editingId ? 'Modifier le lieu' : 'Nouveau lieu'" subtitle="La position GPS sert au calcul des zones tarifaires.">
        <form id="venue-form" wire:submit="save" class="space-y-4">
            <x-ui.input label="Nom" name="name" wire:model="name" placeholder="Stade Olympique d'Ebimpé" />
            <x-ui.input label="Adresse" name="address" wire:model="address" />
            <x-ui.input label="Ville" name="city" wire:model="city" />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input label="Latitude" name="latitude" wire:model="latitude" inputmode="decimal" placeholder="5.4881" />
                <x-ui.input label="Longitude" name="longitude" wire:model="longitude" inputmode="decimal" placeholder="-4.1105" />
            </div>
            <p class="text-[13px] text-ink-tertiary">Astuce : dans Google Maps ou OpenStreetMap, un clic droit sur le lieu affiche ses coordonnées.</p>
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" type="button" x-on:click="open = false">Annuler</x-ui.button>
            <x-ui.button form="venue-form">Enregistrer</x-ui.button>
        </x-slot:footer>
    </x-ui.sheet>
</div>
