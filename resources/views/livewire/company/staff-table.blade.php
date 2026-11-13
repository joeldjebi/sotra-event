<div>
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-3">
            <x-ui.search wire:model.live.debounce.300ms="search" placeholder="Nom, téléphone ou matricule" />
            <x-ui.segmented label="Filtrer par fonction">
                <x-ui.segment :active="$roleFilter === ''" wire:click="$set('roleFilter', '')">Tous <span class="font-normal text-ink-tertiary tabular-nums">{{ $counts->sum() }}</span></x-ui.segment>
                @foreach ($roles as $value => $label)
                    <x-ui.segment wire:key="role-filter-{{ $value }}" :active="$roleFilter === $value" wire:click="$set('roleFilter', '{{ $value }}')">{{ $label }}s <span class="font-normal text-ink-tertiary tabular-nums">{{ (int) ($counts[$value] ?? 0) }}</span></x-ui.segment>
                @endforeach
            </x-ui.segmented>
        </div>
        <div class="flex gap-2">
            <x-ui.button variant="secondary" type="button" wire:click="openImport" icon="document">Importer</x-ui.button>
            <x-ui.button type="button" wire:click="create" icon="id-card">Nouveau membre</x-ui.button>
        </div>
    </div>

    <x-ui.table>
        <x-slot:head>
            <th>Nom</th>
            <th>Fonction</th>
            <th>Téléphone</th>
            <th>Matricule</th>
            <th>Équipe</th>
            <th>Accès mobile</th>
            <th></th>
        </x-slot:head>
        @forelse ($members as $member)
            <tr wire:key="staff-{{ $member->id }}" @class(['hover:bg-fill-subtle', 'opacity-55' => ! $member->is_active])>
                <td class="font-medium">{{ $member->fullName() }}</td>
                <td><x-ui.badge>{{ $member->role->label() }}</x-ui.badge></td>
                <td class="font-mono text-[13px]">{{ $member->phone }}</td>
                <td class="text-ink-secondary">{{ $member->employee_number ?? '—' }}</td>
                <td class="text-ink-secondary">{{ $member->techTeam?->code ?? '—' }}</td>
                <td>
                    @if (! $member->is_active)
                        <x-ui.badge>Désactivé</x-ui.badge>
                    @elseif ($member->role->canSignIn())
                        <x-ui.badge tone="success">Par code SMS</x-ui.badge>
                    @else
                        <span class="text-[13px] text-ink-tertiary">Sans compte</span>
                    @endif
                </td>
                <td class="text-right"><x-ui.button variant="secondary" size="sm" type="button" wire:click="edit({{ $member->id }})">Modifier</x-ui.button></td>
            </tr>
        @empty
            <tr><td colspan="7"><x-ui.empty-state icon="id-card" title="Aucun membre du personnel" description="Ajoutez vos chauffeurs, convoyeurs et techniciens, ou importez-les depuis Excel." /></td></tr>
        @endforelse
        @if ($members->hasPages())
            <x-slot:footer>{{ $members->links() }}</x-slot:footer>
        @endif
    </x-ui.table>

    <x-ui.sheet model="showForm" :title="$editingId ? 'Modifier le membre' : 'Nouveau membre'" subtitle="Convoyeurs et techniciens se connectent avec ce téléphone (code SMS).">
        <form id="staff-form" wire:submit="save" class="space-y-4">
            <x-ui.select label="Fonction" name="role" wire:model.live="role" :options="$roles" />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input label="Prénom" name="first_name" wire:model="first_name" />
                <x-ui.input label="Nom" name="last_name" wire:model="last_name" />
            </div>
            <x-ui.input label="Téléphone" name="phone" wire:model="phone" placeholder="07 00 00 00 01" />
            <x-ui.input label="Matricule" name="employee_number" wire:model="employee_number" />
            @if ($role === 'technicien')
                <x-ui.select label="Équipe technique" name="tech_team_id" wire:model="tech_team_id" :options="$teams" placeholder="Aucune" />
            @endif
            <x-ui.switch label="Actif" description="Désactiver coupe immédiatement l'accès aux apps mobiles." wire:model="is_active" />
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" type="button" x-on:click="open = false">Annuler</x-ui.button>
            <x-ui.button form="staff-form">Enregistrer</x-ui.button>
        </x-slot:footer>
    </x-ui.sheet>

    <x-ui.import-sheet title="Importer le personnel" columns="fonction · prenom · nom · telephone · matricule" />
</div>
