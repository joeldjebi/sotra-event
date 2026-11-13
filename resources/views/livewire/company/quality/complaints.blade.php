<div>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div class="w-full sm:w-80">
            <x-ui.select label="Événement" name="eventId" wire:model.live="eventId" :options="$eventOptions" placeholder="Choisir un événement" />
        </div>
        <div class="flex gap-2">
            <x-ui.badge tone="danger" class="px-3 py-1 text-[13px]">{{ $counts['open'] }} ouverte(s)</x-ui.badge>
            <x-ui.badge tone="success" class="px-3 py-1 text-[13px]">{{ $counts['resolved'] }} traitée(s)</x-ui.badge>
        </div>
    </div>

    <x-ui.segmented label="Statut" class="mb-4">
        <x-ui.segment :active="$status === ''" wire:click="$set('status', '')">Toutes</x-ui.segment>
        @foreach ($statuses as $value => $label)
            <x-ui.segment wire:key="status-{{ $value }}" :active="$status === $value" wire:click="$set('status', '{{ $value }}')">{{ $label }}</x-ui.segment>
        @endforeach
    </x-ui.segmented>

    <x-ui.table>
        <x-slot:head>
            <th>Réclamation</th>
            <th>Offre</th>
            <th>Point</th>
            <th>Bus / heure</th>
            <th>Statut</th>
            <th></th>
        </x-slot:head>
        @forelse ($complaints as $complaint)
            @php($point = $complaint->mission?->boardingPoint ?? $complaint->pass?->boardingPoint)
            <tr wire:key="complaint-{{ $complaint->id }}">
                <td>
                    <p class="font-medium">{{ $complaint->title }}</p>
                    <p class="text-[12px] text-ink-secondary">{{ $complaint->number }} · {{ $complaint->created_at->format('d/m H\hi') }}@if ($complaint->is_collective) · collective, {{ $complaint->affected_count }} passagers @endif</p>
                </td>
                <td>{{ $complaint->pass?->offer->label() ?? $complaint->mission?->offer->label() ?? '—' }}</td>
                <td>{{ $point?->commune ?? '—' }}</td>
                <td class="whitespace-nowrap">
                    @if ($complaint->mission)
                        {{ $complaint->mission->bus ? 'Bus '.$complaint->mission->bus->number : '—' }} · {{ $complaint->mission->direction->label() }} {{ $complaint->mission->planned_departure_at->format('H\hi') }}
                    @else
                        —
                    @endif
                </td>
                <td><x-ui.badge :tone="$complaint->status->tone()">{{ $complaint->status->label() }}</x-ui.badge></td>
                <td class="text-right">
                    <x-ui.button :variant="$complaint->status->value === 'traitee' ? 'secondary' : 'primary'" size="sm" type="button" wire:click="handle({{ $complaint->id }})">
                        {{ $complaint->status->value === 'traitee' ? 'Voir' : 'Traiter' }}
                    </x-ui.button>
                </td>
            </tr>
        @empty
            <tr><td colspan="6"><x-ui.empty-state icon="lifebuoy" title="Aucune réclamation" description="Les réclamations des spectateurs et les réclamations collectives (retard de plus de 20 min) apparaîtront ici." /></td></tr>
        @endforelse
        @if ($complaints->hasPages())
            <x-slot:footer>{{ $complaints->links() }}</x-slot:footer>
        @endif
    </x-ui.table>

    <x-ui.sheet model="showHandling" :title="$handling ? $handling->number.' · '.$handling->title : 'Réclamation'" :subtitle="$handling?->status->label()">
        @if ($handling)
            <div class="space-y-5 text-[14px]">
                <dl class="grid grid-cols-2 gap-4">
                    <div><dt class="text-ink-secondary">Déposée</dt><dd>{{ $handling->created_at->format('d/m/Y H\hi') }}</dd></div>
                    <div><dt class="text-ink-secondary">{{ $handling->is_collective ? 'Passagers concernés' : 'Spectateur' }}</dt>
                        <dd>{{ $handling->is_collective ? $handling->affected_count : ($handling->customer ? ($handling->customer->fullName() ?: $handling->customer->phone) : "—") }}</dd></div>
                    @if ($handling->pass)
                        <div><dt class="text-ink-secondary">Pass</dt><dd class="font-mono">{{ $handling->pass->code }}</dd></div>
                    @endif
                    @if ($handling->handler)
                        <div><dt class="text-ink-secondary">Traitée par</dt><dd>{{ $handling->handler->name }}</dd></div>
                    @endif
                </dl>
                @if ($handling->details)
                    <p class="rounded-xl bg-fill px-4 py-3">{{ $handling->details }}</p>
                @endif

                @if ($handling->status->value === 'traitee')
                    <div>
                        <p class="text-ink-secondary">Réponse envoyée le {{ $handling->resolved_at?->format('d/m H\hi') }}</p>
                        <p class="mt-1">{{ $handling->resolution }}</p>
                    </div>
                @else
                    <form id="resolve-form" wire:submit="resolve">
                        <x-ui.textarea label="Réponse au spectateur (envoyée par SMS)" name="resolution" wire:model="resolution" rows="4"
                                       placeholder="Ex. : Nous vous prions de nous excuser ; votre trajet retour vous est remboursé sous 72 h." />
                    </form>
                @endif
            </div>
        @endif
        <x-slot:footer>
            <x-ui.button variant="secondary" type="button" x-on:click="open = false">Fermer</x-ui.button>
            @if ($handling && $handling->status->value !== 'traitee')
                <x-ui.button form="resolve-form">Marquer traitée</x-ui.button>
            @endif
        </x-slot:footer>
    </x-ui.sheet>
</div>
