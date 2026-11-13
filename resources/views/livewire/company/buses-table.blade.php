<div>
    <div class="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        <button type="button" wire:click="$set('statusFilter', '')" @class(['rounded-[var(--radius-card)] p-4 text-left ring-1 transition', 'bg-marine-900 text-white ring-marine-900' => $statusFilter === '', 'bg-surface ring-hairline hover:bg-canvas' => $statusFilter !== ''])>
            <p @class(['text-[13px]', 'text-white/70' => $statusFilter === '', 'text-ink-secondary' => $statusFilter !== ''])>Toute la flotte</p>
            <p class="mt-1 text-[26px] font-semibold tracking-tight tabular-nums">{{ $total }}</p>
        </button>
        @foreach ($statuses as $value => $label)
            <button type="button" wire:key="status-{{ $value }}" wire:click="$set('statusFilter', '{{ $value }}')" @class(['rounded-[var(--radius-card)] p-4 text-left ring-1 transition', 'bg-marine-900 text-white ring-marine-900' => $statusFilter === $value, 'bg-surface ring-hairline hover:bg-canvas' => $statusFilter !== $value])>
                <p @class(['text-[13px]', 'text-white/70' => $statusFilter === $value, 'text-ink-secondary' => $statusFilter !== $value])>{{ $label }}</p>
                <p class="mt-1 text-[26px] font-semibold tracking-tight tabular-nums">{{ (int) ($counts[$value] ?? 0) }}</p>
            </button>
        @endforeach
    </div>

    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <x-ui.search wire:model.live.debounce.300ms="search" placeholder="Numéro ou immatriculation" />
        <div class="flex gap-2">
            <x-ui.button variant="secondary" type="button" wire:click="openImport" icon="document">Importer</x-ui.button>
            <x-ui.button type="button" wire:click="create" icon="bus">Nouveau bus</x-ui.button>
        </div>
    </div>

    <x-ui.table>
        <x-slot:head>
            <th>Bus</th>
            <th>Immatriculation</th>
            <th class="text-right">Places</th>
            <th>Gamme</th>
            <th>Dépôt</th>
            <th>Statut</th>
            <th></th>
        </x-slot:head>

        @forelse ($buses as $bus)
            <tr wire:key="bus-{{ $bus->id }}" class="hover:bg-canvas/50">
                <td class="font-semibold">Bus {{ $bus->number }}</td>
                <td class="font-mono text-[13px]">{{ $bus->plate }}</td>
                <td class="text-right tabular-nums">{{ $bus->capacity }}</td>
                <td><x-ui.badge :tone="$bus->category->value === 'standard' ? 'neutral' : 'brand'">{{ $bus->category->label() }}</x-ui.badge></td>
                <td class="text-ink-secondary">{{ $bus->depot?->name ?? '—' }}</td>
                <td><x-ui.badge :tone="$bus->status->tone()">{{ $bus->status->label() }}</x-ui.badge></td>
                <td class="text-right whitespace-nowrap">
                    <x-ui.button variant="ghost" size="sm" type="button" wire:click="delete({{ $bus->id }})" wire:confirm="Supprimer le bus {{ $bus->number }} ? Préférez le statut « Hors service » s'il a déjà roulé.">Supprimer</x-ui.button>
                    <x-ui.button variant="secondary" size="sm" type="button" wire:click="edit({{ $bus->id }})">Modifier</x-ui.button>
                </td>
            </tr>
        @empty
            <tr><td colspan="7"><x-ui.empty-state icon="bus" title="Aucun bus" description="Ajoutez vos bus un par un ou importez toute la flotte depuis un fichier Excel." /></td></tr>
        @endforelse

        @if ($buses->hasPages())
            <x-slot:footer>{{ $buses->links() }}</x-slot:footer>
        @endif
    </x-ui.table>

    <x-ui.sheet model="showForm" :title="$editingId ? 'Modifier le bus' : 'Nouveau bus'">
        <form id="bus-form" wire:submit="save" class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input label="Numéro de parc" name="number" wire:model="number" placeholder="12" />
                <x-ui.input label="Immatriculation" name="plate" wire:model="plate" placeholder="1234 AB 01" />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input label="Places" name="capacity" type="number" min="10" max="200" wire:model="capacity" />
                <x-ui.select label="Gamme" name="category" wire:model="category" :options="$categories" />
            </div>
            <x-ui.select label="Statut" name="status" wire:model="status" :options="$statuses" />
            <x-ui.select label="Dépôt" name="depot_id" wire:model="depot_id" :options="$depots" placeholder="Aucun" />
            <x-ui.textarea label="Notes" name="notes" wire:model="notes" />
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" type="button" x-on:click="open = false">Annuler</x-ui.button>
            <x-ui.button form="bus-form">Enregistrer</x-ui.button>
        </x-slot:footer>
    </x-ui.sheet>

    <x-ui.import-sheet title="Importer la flotte" columns="numero · immatriculation · capacite · gamme · statut · depot" />
</div>
