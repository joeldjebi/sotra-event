<div>
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-3">
            <x-ui.search wire:model.live.debounce.300ms="search" placeholder="Rechercher un événement" />
            <div class="w-52"><x-ui.select label="" name="statusFilter" wire:model.live="statusFilter" :options="$statuses" placeholder="Tous les statuts" /></div>
        </div>
        <x-ui.button :href="route('admin.events.create')" icon="calendar">Nouvel événement</x-ui.button>
    </div>

    <x-ui.table>
        <x-slot:head>
            <th>Événement</th>
            <th>Date</th>
            <th>Compagnie</th>
            <th>Promoteur</th>
            <th class="text-right">Points</th>
            <th>Statut</th>
        </x-slot:head>
        @forelse ($events as $event)
            <tr wire:key="event-{{ $event->id }}" class="cursor-pointer hover:bg-fill-subtle" onclick="window.location='{{ route('admin.events.edit', $event) }}'">
                <td>
                    <a href="{{ route('admin.events.edit', $event) }}" class="font-medium hover:text-brand-text">{{ $event->name }}</a>
                    <p class="text-[13px] text-ink-secondary">{{ $event->venue->name }}</p>
                </td>
                <td class="whitespace-nowrap">
                    <p>{{ $event->starts_at->translatedFormat('D j M Y') }}</p>
                    <p class="text-[13px] text-ink-secondary">{{ $event->starts_at->format('H\hi') }}</p>
                </td>
                <td>{{ $event->company->name }}</td>
                <td class="text-ink-secondary">{{ $event->promoter->name }}</td>
                <td class="text-right tabular-nums">{{ $event->boarding_points_count }}</td>
                <td><x-ui.badge :tone="$event->status->tone()">{{ $event->status->label() }}</x-ui.badge></td>
            </tr>
        @empty
            <tr><td colspan="6">
                <x-ui.empty-state icon="calendar" title="Aucun événement" description="Créez un événement : informations, points d'embarquement, grille tarifaire, puis canaux et publication.">
                    <x-ui.button :href="route('admin.events.create')">Créer un événement</x-ui.button>
                </x-ui.empty-state>
            </td></tr>
        @endforelse
        @if ($events->hasPages())
            <x-slot:footer>{{ $events->links() }}</x-slot:footer>
        @endif
    </x-ui.table>
</div>
