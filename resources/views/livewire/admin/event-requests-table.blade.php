<div>
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-3">
            <x-ui.search wire:model.live.debounce.300ms="search" placeholder="Événement ou promoteur" />
            <x-ui.segmented label="Filtrer par statut">
                @foreach ($statuses as $value => $label)
                    <x-ui.segment wire:key="filter-{{ $value }}" :active="$statusFilter === $value" wire:click="$set('statusFilter', '{{ $value }}')">
                        {{ $label }}@if ($value === 'soumise' && $pendingCount > 0) ({{ $pendingCount }})@endif
                    </x-ui.segment>
                @endforeach
                <x-ui.segment :active="$statusFilter === 'toutes'" wire:click="$set('statusFilter', 'toutes')">Toutes</x-ui.segment>
            </x-ui.segmented>
        </div>
    </div>

    <x-ui.table>
        <x-slot:head>
            <th>Événement</th>
            <th>Promoteur</th>
            <th>Date et lieu</th>
            <th class="text-right">Public · Pass estimés</th>
            <th>Statut</th>
            <th></th>
        </x-slot:head>

        @forelse ($requests as $request)
            <tr wire:key="admin-request-{{ $request->id }}" class="hover:bg-fill-subtle">
                <td>
                    <p class="font-medium">{{ $request->name }}</p>
                    @if ($request->description)<p class="max-w-72 text-[12px] text-ink-secondary">{{ \Illuminate\Support\Str::limit($request->description, 120) }}</p>@endif
                    @if ($request->desired_communes)<p class="text-[12px] text-ink-secondary">Communes : {{ $request->desired_communes }}</p>@endif
                </td>
                <td>
                    <p>{{ $request->promoter->name }}</p>
                    <p class="text-[12px] text-ink-secondary">{{ $request->submitter?->name }} · {{ $request->created_at->format('d/m/Y') }}</p>
                </td>
                <td>
                    <p class="whitespace-nowrap">{{ $request->starts_at->translatedFormat('d M Y · H\hi') }}</p>
                    <p class="text-[12px] text-ink-secondary">{{ $request->venueLabel() }}</p>
                </td>
                <td class="text-right tabular-nums">
                    {{ $request->expected_attendance ? number_format($request->expected_attendance, 0, ',', ' ') : '—' }}
                    · {{ $request->estimated_passes ? number_format($request->estimated_passes, 0, ',', ' ') : '—' }}
                </td>
                <td>
                    <x-ui.badge :tone="$request->status->tone()">{{ $request->status->label() }}</x-ui.badge>
                    @if ($request->review_note)<p class="mt-1 max-w-56 text-[12px] text-ink-secondary">{{ $request->review_note }}</p>@endif
                </td>
                <td class="text-right whitespace-nowrap">
                    @if ($request->isPending())
                        <x-ui.button size="sm" type="button" wire:click="accept({{ $request->id }})">Créer l'événement</x-ui.button>
                        <x-ui.button variant="danger-tinted" size="sm" type="button" class="ml-1" wire:click="startRejection({{ $request->id }})">Refuser</x-ui.button>
                    @elseif ($request->event)
                        <x-ui.button variant="secondary" size="sm" :href="route('admin.events.edit', $request->event)">Voir l'événement</x-ui.button>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6"><x-ui.empty-state icon="calendar" title="Aucune demande" description="Les événements proposés par les promoteurs s'afficheront ici." /></td></tr>
        @endforelse

        @if ($requests->hasPages())
            <x-slot:footer>{{ $requests->links() }}</x-slot:footer>
        @endif
    </x-ui.table>

    <x-ui.sheet model="showForm" title="Refuser la demande">
        <form id="reject-form" wire:submit="reject" class="space-y-4">
            <x-ui.textarea label="Motif, envoyé au promoteur par SMS" name="reason" wire:model="reason" />
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" type="button" x-on:click="open = false">Annuler</x-ui.button>
            <x-ui.button variant="danger" form="reject-form">Refuser</x-ui.button>
        </x-slot:footer>
    </x-ui.sheet>
</div>
