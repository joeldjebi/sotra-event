<x-layouts.app :title="'Bonjour, '.Str::before(auth()->user()->name, ' ')" :subtitle="$company?->name">
    <div class="grid gap-4 sm:grid-cols-3">
        @foreach ($staffCounts as $label => $count)
            <x-ui.kpi wire:key="staff-{{ $loop->index }}" :label="$label.'s'" :value="$count" hint="dans votre compagnie" />
        @endforeach
    </div>

    <x-ui.card class="mt-6" padding="p-0">
        <x-ui.empty-state icon="calendar" title="Aucun événement proposé"
                          description="Les événements proposés par ACCES EVENT apparaîtront ici. Vous pourrez les accepter ou ouvrir une négociation." />
    </x-ui.card>
</x-layouts.app>
