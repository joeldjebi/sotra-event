<x-layouts.app :title="'Annuler ou reporter · '.$event->name" subtitle="Événements">
    @livewire('admin.events.disruption', ['event' => $event])
</x-layouts.app>
