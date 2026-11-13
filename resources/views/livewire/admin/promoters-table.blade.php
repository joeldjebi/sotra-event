<div>
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <x-ui.search wire:model.live.debounce.300ms="search" placeholder="Rechercher un promoteur" />
        <x-ui.button wire:click="create" type="button" icon="sparkles">Nouveau promoteur</x-ui.button>
    </div>

    <x-ui.table>
        <x-slot:head>
            <th>Promoteur</th>
            <th>Contact</th>
            <th>Chefs de projet transport</th>
            <th>Statut</th>
            <th></th>
        </x-slot:head>

        @forelse ($promoters as $promoter)
            <tr wire:key="promoter-{{ $promoter->id }}" class="hover:bg-canvas/50">
                <td class="font-medium">{{ $promoter->name }}</td>
                <td class="text-ink-secondary">
                    <p>{{ $promoter->email ?? '—' }}</p>
                    <p class="text-[13px]">{{ $promoter->phone }}</p>
                </td>
                <td>
                    @forelse ($promoter->users as $lead)
                        <span wire:key="lead-{{ $lead->id }}" class="mr-1 mb-1 inline-flex"><x-ui.badge>{{ $lead->name }}</x-ui.badge></span>
                    @empty
                        <span class="text-[13px] text-ink-tertiary">Aucun — à créer dans Utilisateurs & rôles</span>
                    @endforelse
                </td>
                <td><x-ui.badge :tone="$promoter->is_active ? 'success' : 'neutral'">{{ $promoter->is_active ? 'Actif' : 'Inactif' }}</x-ui.badge></td>
                <td class="text-right"><x-ui.button variant="secondary" size="sm" type="button" wire:click="edit({{ $promoter->id }})">Modifier</x-ui.button></td>
            </tr>
        @empty
            <tr><td colspan="5"><x-ui.empty-state icon="megaphone" title="Aucun promoteur" description="Ajoutez les organisateurs d'événements partenaires." /></td></tr>
        @endforelse

        @if ($promoters->hasPages())
            <x-slot:footer>{{ $promoters->links() }}</x-slot:footer>
        @endif
    </x-ui.table>

    <x-ui.sheet model="showForm" :title="$editingId ? 'Modifier le promoteur' : 'Nouveau promoteur'">
        <form id="promoter-form" wire:submit="save" class="space-y-4">
            <x-ui.input label="Nom" name="name" wire:model="name" />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input label="E-mail" name="email" type="email" wire:model="email" />
                <x-ui.input label="Téléphone" name="phone" wire:model="phone" />
            </div>
            <x-ui.input label="Adresse" name="address" wire:model="address" />
            <x-ui.switch label="Promoteur actif" wire:model="is_active" />
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" type="button" x-on:click="open = false">Annuler</x-ui.button>
            <x-ui.button form="promoter-form">Enregistrer</x-ui.button>
        </x-slot:footer>
    </x-ui.sheet>
</div>
