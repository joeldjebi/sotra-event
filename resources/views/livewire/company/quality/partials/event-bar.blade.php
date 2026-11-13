{{-- Sélecteur d'événement des écrans Qualité, avec action facultative à droite. --}}
<div class="mb-6 flex flex-wrap items-end justify-between gap-3">
    <div class="w-full sm:w-80">
        <x-ui.select label="Événement" name="eventId" wire:model.live="eventId" :options="$eventOptions" placeholder="Choisir un événement" />
    </div>
    @if ($event ?? null)
        <x-ui.button variant="secondary" icon="document" :href="route('company.quality.report', ['event' => $event->id])">Générer le rapport PDF</x-ui.button>
    @endif
</div>
