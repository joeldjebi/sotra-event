<div>
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <x-ui.search wire:model.live.debounce.300ms="search" placeholder="Rechercher un dépôt" />
        <x-ui.button type="button" wire:click="create" icon="building">Nouveau dépôt</x-ui.button>
    </div>

    <x-ui.table>
        <x-slot:head>
            <th>Dépôt</th>
            <th>Téléphone</th>
            <th class="text-right">Bus rattachés</th>
            <th>Position</th>
            <th></th>
        </x-slot:head>
        @forelse ($depots as $depot)
            <tr wire:key="depot-{{ $depot->id }}" class="hover:bg-fill-subtle">
                <td>
                    <p class="font-medium">{{ $depot->name }}</p>
                    <p class="text-[13px] text-ink-secondary">{{ $depot->address }}</p>
                </td>
                <td class="text-ink-secondary">{{ $depot->phone ?? '—' }}</td>
                <td class="text-right tabular-nums">{{ $depot->buses_count }}</td>
                <td class="text-[13px]">
                    @if ($depot->location)
                        <x-ui.badge tone="success">Localisé</x-ui.badge>
                    @else
                        <x-ui.badge tone="warning">À localiser</x-ui.badge>
                    @endif
                </td>
                <td class="text-right"><x-ui.button variant="secondary" size="sm" type="button" wire:click="edit({{ $depot->id }})">Modifier</x-ui.button></td>
            </tr>
        @empty
            <tr><td colspan="5"><x-ui.empty-state icon="building" title="Aucun dépôt" description="Les dépôts servent de point d'attache aux bus et aux bus de réserve." /></td></tr>
        @endforelse
        @if ($depots->hasPages())
            <x-slot:footer>{{ $depots->links() }}</x-slot:footer>
        @endif
    </x-ui.table>

    <x-ui.sheet model="showForm" :title="$editingId ? 'Modifier le dépôt' : 'Nouveau dépôt'" subtitle="La position sert à proposer le bus de réserve le plus proche en cas de panne.">
        <form id="depot-form" wire:submit="save" class="space-y-4">
            <x-ui.input label="Nom" name="name" wire:model="name" placeholder="Dépôt de Yopougon" />
            <x-ui.input label="Adresse" name="address" wire:model="address" />
            <x-ui.input label="Téléphone du dépôt" name="phone" wire:model="phone" hint="Appelé depuis l'app convoyeur en cas de panne (CV-18)." />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input label="Latitude" name="latitude" wire:model="latitude" inputmode="decimal" />
                <x-ui.input label="Longitude" name="longitude" wire:model="longitude" inputmode="decimal" />
            </div>
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" type="button" x-on:click="open = false">Annuler</x-ui.button>
            <x-ui.button form="depot-form">Enregistrer</x-ui.button>
        </x-slot:footer>
    </x-ui.sheet>
</div>
