<div>
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <x-ui.search wire:model.live.debounce.300ms="search" placeholder="Code d'équipe" />
        <x-ui.button type="button" wire:click="create" icon="wrench">Nouvelle équipe</x-ui.button>
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($teams as $team)
            <x-ui.card wire:key="team-{{ $team->id }}" padding="p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[22px] font-semibold tracking-tight">Équipe {{ $team->code }}</p>
                        <p class="text-[14px] text-ink-secondary">{{ $team->type->label() }}</p>
                    </div>
                    <x-ui.badge :tone="$team->status->tone()">{{ $team->status->label() }}</x-ui.badge>
                </div>
                <div class="mt-4 space-y-1.5">
                    @forelse ($team->members as $member)
                        <p wire:key="member-{{ $member->id }}" class="flex items-center gap-2 text-[14px]">
                            <span class="grid size-6 place-items-center rounded-full bg-fill text-[11px] font-semibold">{{ mb_substr($member->first_name, 0, 1) }}{{ mb_substr($member->last_name, 0, 1) }}</span>
                            {{ $member->fullName() }}
                        </p>
                    @empty
                        <p class="text-[13px] text-ink-tertiary">Aucun technicien affecté.</p>
                    @endforelse
                </div>
                <div class="mt-5 flex items-center justify-between border-t border-hairline pt-4">
                    <p class="text-[13px] text-ink-secondary">{{ $team->depot?->name ?? 'Sans dépôt' }}</p>
                    <x-ui.button variant="secondary" size="sm" type="button" wire:click="edit({{ $team->id }})">Modifier</x-ui.button>
                </div>
            </x-ui.card>
        @empty
            <x-ui.card class="md:col-span-2 xl:col-span-3" padding="p-0">
                <x-ui.empty-state icon="wrench" title="Aucune équipe technique" description="Créez vos équipes de dépannage (ex. T1, T3) et affectez-leur des techniciens." />
            </x-ui.card>
        @endforelse
    </div>
    @if ($teams->hasPages())
        <div class="mt-4">{{ $teams->links() }}</div>
    @endif

    <x-ui.sheet model="showForm" :title="$editingId ? 'Modifier l\'équipe' : 'Nouvelle équipe'">
        <form id="team-form" wire:submit="save" class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input label="Code" name="code" wire:model="code" placeholder="T3" />
                <x-ui.select label="Disponibilité" name="status" wire:model="status" :options="$statuses" />
            </div>
            <x-ui.select label="Type" name="type" wire:model="type" :options="$types" />
            <x-ui.select label="Dépôt de rattachement" name="depot_id" wire:model="depot_id" :options="$depots" placeholder="Aucun" />
            <div>
                <p class="mb-1.5 text-[13px] font-medium text-ink-secondary">Techniciens</p>
                <div class="max-h-72 overflow-y-auto rounded-xl bg-canvas px-3 py-2">
                    @forelse ($technicians as $technician)
                        <x-ui.checkbox wire:key="tech-{{ $technician->id }}" :label="$technician->fullName()" value="{{ $technician->id }}" wire:model="members"
                                       :description="$technician->techTeam && $technician->techTeam->id !== $editingId ? 'Actuellement dans l\'équipe '.$technician->techTeam->code : null" />
                    @empty
                        <p class="py-2 text-[13px] text-ink-tertiary">Aucun technicien actif. Ajoutez-en dans Chauffeurs & convoyeurs.</p>
                    @endforelse
                </div>
                @error('members.*') <p class="mt-1.5 text-[13px] text-danger">{{ $message }}</p> @enderror
            </div>
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" type="button" x-on:click="open = false">Annuler</x-ui.button>
            <x-ui.button form="team-form">Enregistrer</x-ui.button>
        </x-slot:footer>
    </x-ui.sheet>
</div>
