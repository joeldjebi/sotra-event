{{-- Formulaire d'une zone de dépose / reprise (trait EditsDropZones). --}}
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
