<div>
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div class="w-full sm:w-64">
            <x-ui.select label="Type d'objet" name="subjectType" wire:model.live="subjectType" :options="$subjectTypes" placeholder="Tous" />
        </div>
        <p class="text-[13px] text-ink-tertiary">Journal non modifiable : aucune entrée ne peut être modifiée ni supprimée.</p>
    </div>

    <x-ui.table>
        <x-slot:head>
            <th>Date</th>
            <th>Auteur</th>
            <th>Action</th>
            <th>Objet</th>
            <th>Détail</th>
        </x-slot:head>

        @forelse ($logs as $log)
            <tr wire:key="log-{{ $log->id }}" class="align-top">
                <td class="text-[13px] whitespace-nowrap text-ink-secondary">{{ $log->created_at?->format('d/m/Y H:i:s') }}</td>
                <td class="text-[13px]">{{ $log->actor?->getAttribute('name') ?? $log->actor?->getAttribute('phone') ?? 'Système' }}</td>
                <td><x-ui.badge :tone="$log->action === 'deleted' ? 'danger' : 'neutral'">{{ \App\Livewire\Admin\AuditLogTable::actionLabel($log->action) }}</x-ui.badge></td>
                <td class="text-[13px] whitespace-nowrap">{{ $subjectTypes[$log->auditable_type] ?? class_basename((string) $log->auditable_type) }} @if ($log->auditable_id) <span class="text-ink-tertiary">#{{ $log->auditable_id }}</span>@endif</td>
                <td class="text-[13px]">
                    @php($keys = array_unique(array_merge(array_keys($log->old_values ?? []), array_keys($log->new_values ?? []))))
                    @foreach (array_slice($keys, 0, 6) as $key)
                        <p wire:key="log-{{ $log->id }}-{{ $key }}" class="truncate">
                            <span class="text-ink-secondary">{{ $key }}</span>
                            @if ($log->action === 'updated' || $log->action === 'roles_changed')
                                <span class="text-ink-tertiary">{{ json_encode($log->old_values[$key] ?? null, JSON_UNESCAPED_UNICODE) }}</span> → {{ json_encode($log->new_values[$key] ?? null, JSON_UNESCAPED_UNICODE) }}
                            @else
                                : {{ \Illuminate\Support\Str::limit((string) json_encode($log->new_values[$key] ?? $log->old_values[$key] ?? null, JSON_UNESCAPED_UNICODE), 60) }}
                            @endif
                        </p>
                    @endforeach
                    @if (count($keys) > 6)<p class="text-ink-tertiary">+ {{ count($keys) - 6 }} champs</p>@endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5"><x-ui.empty-state icon="shield" title="Journal vide" /></td></tr>
        @endforelse

        @if ($logs->hasPages())
            <x-slot:footer>{{ $logs->links() }}</x-slot:footer>
        @endif
    </x-ui.table>
</div>
