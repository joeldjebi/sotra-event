<div>
    <div class="mb-5 flex flex-wrap items-center gap-3">
        <x-ui.search wire:model.live.debounce.300ms="search" placeholder="Nom ou numéro de série" />
        <div class="w-60"><x-ui.select label="" name="company" wire:model.live="company" :options="$companies" placeholder="Toutes les compagnies" /></div>
    </div>

    <x-ui.table>
        <x-slot:head><th>Terminal</th><th>Compagnie</th><th>Convoyeur</th><th>Application</th><th>Dernière synchronisation</th><th>État</th></x-slot:head>
        @forelse ($devices as $device)
            @php($stale = ! $device->isBlocked() && ($device->last_sync_at === null || $device->last_sync_at->lt($staleBefore)))
            <tr wire:key="device-{{ $device->id }}">
                <td><p class="font-medium">{{ $device->label }}</p><p class="text-[12px] text-ink-secondary">{{ $device->serial_number }}{{ $device->model ? ' · '.$device->model : '' }}</p></td>
                <td>{{ $device->company->name }}</td>
                <td class="text-ink-secondary">{{ $device->staff ? $device->staff->first_name.' '.$device->staff->last_name : '—' }}</td>
                <td class="tabular-nums">{{ $device->app_version ?? '—' }}</td>
                <td>{{ $device->last_sync_at?->diffForHumans() ?? 'Jamais' }}</td>
                <td>
                    @if ($device->isBlocked())
                        <x-ui.badge tone="danger">Bloqué</x-ui.badge>
                        @if ($device->blocked_reason)<p class="mt-1 text-[12px] text-ink-secondary">{{ $device->blocked_reason }}</p>@endif
                    @elseif ($stale)
                        <x-ui.badge tone="warning">À vérifier</x-ui.badge>
                    @else
                        <x-ui.badge tone="success">Actif</x-ui.badge>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6"><x-ui.empty-state icon="device" title="Aucun terminal" description="Les compagnies enregistrent leurs PDA et smartphones dans leur espace." /></td></tr>
        @endforelse
        @if ($devices->hasPages())
            <x-slot:footer>{{ $devices->links() }}</x-slot:footer>
        @endif
    </x-ui.table>
    <p class="mt-3 text-[13px] text-ink-secondary">« À vérifier » : terminal actif sans synchronisation depuis plus de {{ \App\Livewire\Admin\DevicesOverview::STALE_HOURS }} heures.</p>
</div>
