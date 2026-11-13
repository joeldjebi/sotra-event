<div>
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <x-ui.search wire:model.live.debounce.300ms="search" placeholder="Nom ou numéro de série" />
        <x-ui.button type="button" wire:click="create" icon="device">Nouveau terminal</x-ui.button>
    </div>

    <x-ui.table>
        <x-slot:head>
            <th>Terminal</th>
            <th>N° de série</th>
            <th>Attribué à</th>
            <th>Dernière synchro</th>
            <th>Statut</th>
            <th></th>
        </x-slot:head>
        @forelse ($devices as $device)
            <tr wire:key="device-{{ $device->id }}" class="hover:bg-canvas/50">
                <td>
                    <p class="font-medium">{{ $device->label }}</p>
                    <p class="text-[13px] text-ink-secondary">{{ $device->model ?? '—' }} @if ($device->app_version) · app {{ $device->app_version }} @endif</p>
                </td>
                <td class="font-mono text-[13px]">{{ $device->serial_number }}</td>
                <td>{{ $device->staff?->fullName() ?? '—' }}</td>
                <td class="text-[13px] text-ink-secondary">{{ $device->last_sync_at?->diffForHumans() ?? 'Jamais' }}</td>
                <td>
                    @if ($device->isBlocked())
                        <x-ui.badge tone="danger" title="{{ $device->blocked_reason }}">Bloqué</x-ui.badge>
                    @else
                        <x-ui.badge tone="success">Actif</x-ui.badge>
                    @endif
                </td>
                <td class="text-right whitespace-nowrap">
                    @if ($device->isBlocked())
                        <x-ui.button variant="ghost" size="sm" type="button" wire:click="unblock({{ $device->id }})" wire:confirm="Débloquer ce terminal ?">Débloquer</x-ui.button>
                    @else
                        <x-ui.button variant="ghost" size="sm" type="button" class="text-danger" wire:click="confirmBlock({{ $device->id }})">Bloquer</x-ui.button>
                    @endif
                    <x-ui.button variant="secondary" size="sm" type="button" wire:click="edit({{ $device->id }})">Modifier</x-ui.button>
                </td>
            </tr>
        @empty
            <tr><td colspan="6"><x-ui.empty-state icon="device" title="Aucun terminal" description="Enregistrez les PDA et smartphones remis aux convoyeurs et techniciens." /></td></tr>
        @endforelse
        @if ($devices->hasPages())
            <x-slot:footer>{{ $devices->links() }}</x-slot:footer>
        @endif
    </x-ui.table>

    <x-ui.sheet model="showForm" :title="$editingId ? 'Modifier le terminal' : 'Nouveau terminal'">
        <form id="device-form" wire:submit="save" class="space-y-4">
            <x-ui.input label="Nom" name="label" wire:model="label" placeholder="PDA 12" />
            <x-ui.input label="Numéro de série" name="serial_number" wire:model="serial_number" />
            <x-ui.input label="Modèle" name="model" wire:model="model" placeholder="Zebra TC21" />
            <x-ui.select label="Attribué à" name="staff_id" wire:model="staff_id" :options="$assignable" placeholder="Personne" />
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" type="button" x-on:click="open = false">Annuler</x-ui.button>
            <x-ui.button form="device-form">Enregistrer</x-ui.button>
        </x-slot:footer>
    </x-ui.sheet>

    <x-ui.sheet model="showBlock" title="Bloquer le terminal" subtitle="La personne à qui il est attribué sera déconnectée des apps mobiles.">
        <form id="block-form" wire:submit="block" class="space-y-4">
            <x-ui.input label="Motif" name="blocked_reason" wire:model="blocked_reason" placeholder="Terminal perdu le 15/11" />
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" type="button" x-on:click="open = false">Annuler</x-ui.button>
            <x-ui.button variant="danger" form="block-form">Bloquer</x-ui.button>
        </x-slot:footer>
    </x-ui.sheet>
</div>
