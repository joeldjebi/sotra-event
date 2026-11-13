<div>
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-3">
            <x-ui.search wire:model.live.debounce.300ms="search" placeholder="Nom, téléphone ou matricule" />
            <div class="inline-flex rounded-[10px] bg-fill p-0.5 text-[13px] font-medium">
                <button type="button" wire:click="$set('roleFilter', '')" @class(['rounded-[8px] px-3 py-1.5 transition', 'bg-surface shadow-sm' => $roleFilter === '', 'text-ink-secondary' => $roleFilter !== ''])>Tous <span class="text-ink-tertiary">{{ $counts->sum() }}</span></button>
                @foreach ($roles as $value => $label)
                    <button type="button" wire:key="role-filter-{{ $value }}" wire:click="$set('roleFilter', '{{ $value }}')" @class(['rounded-[8px] px-3 py-1.5 transition', 'bg-surface shadow-sm' => $roleFilter === $value, 'text-ink-secondary' => $roleFilter !== $value])>{{ $label }}s <span class="text-ink-tertiary">{{ (int) ($counts[$value] ?? 0) }}</span></button>
                @endforeach
            </div>
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
            <tr wire:key="staff-{{ $member->id }}" @class(['hover:bg-canvas/50', 'opacity-55' => ! $member->is_active])>
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
