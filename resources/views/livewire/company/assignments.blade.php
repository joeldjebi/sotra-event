<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div class="flex flex-wrap items-end gap-3">
            <div class="w-full sm:w-80"><x-ui.select label="Événement" name="eventId" wire:model.live="eventId" :options="$eventOptions" placeholder="Choisir un événement" /></div>
            <x-ui.segmented label="Sens">
                <x-ui.segment :active="$direction === 'aller'" wire:click="$set('direction', 'aller')">Aller</x-ui.segment>
                <x-ui.segment :active="$direction === 'retour'" wire:click="$set('direction', 'retour')">Retour</x-ui.segment>
            </x-ui.segmented>
        </div>
        <x-ui.button variant="secondary" :href="route('company.assignments.fleet')" icon="bus">Flotte</x-ui.button>
    </div>

    @if (! $event)
        <x-ui.card padding="p-0"><x-ui.empty-state icon="bus" title="Aucun événement à affecter" description="Les événements acceptés ou en vente de votre compagnie apparaîtront ici." /></x-ui.card>
    @elseif ($total === 0)
        <x-ui.card padding="p-0">
            <x-ui.empty-state icon="route" title="Plan de transport non généré" description="Générez d'abord les missions depuis le Plan de transport : le nombre de bus par point découle des ventes.">
                <x-ui.button :href="route('company.transport-plan')">Plan de transport</x-ui.button>
            </x-ui.empty-state>
        </x-ui.card>
    @else
        <div class="flex items-center gap-3">
            <x-ui.badge :tone="$staffed === $total ? 'success' : 'warning'">{{ $staffed }} / {{ $total }} missions complètes</x-ui.badge>
            <p class="text-[13px] text-ink-secondary">Chaque choix est enregistré immédiatement. Un bus ou un membre d'équipage ne peut être que sur une mission {{ $direction }}.</p>
        </div>

        @foreach ($missionsByPoint as $pointLabel => $missions)
            <x-ui.table wire:key="point-{{ $loop->index }}">
                <x-slot:head>
                    <th>{{ $pointLabel }}</th>
                    <th>Départ</th>
                    <th>Passagers</th>
                    <th>Bus</th>
                    <th>Chauffeur</th>
                    <th>Convoyeur</th>
                    <th>Terminal</th>
                </x-slot:head>
                @foreach ($missions as $mission)
                    <tr wire:key="mission-{{ $mission->id }}">
                        <td class="whitespace-nowrap">
                            <p class="font-medium">{{ $mission->offer->label() }} n° {{ $mission->sequence }}</p>
                            @if ($mission->isFullyStaffed())
                                <x-ui.badge tone="success">Prête</x-ui.badge>
                            @else
                                <x-ui.badge tone="warning">À compléter</x-ui.badge>
                            @endif
                        </td>
                        <td class="tabular-nums whitespace-nowrap">{{ $mission->planned_departure_at->format('H\hi') }}</td>
                        <td class="tabular-nums whitespace-nowrap">
                            @if ($mission->direction->value === 'aller')
                                {{ $mission->outbound_passes_count }} / {{ $mission->seats }}
                            @else
                                <span class="text-ink-secondary">{{ $mission->seats }} places</span>
                            @endif
                        </td>
                        @foreach ([
                            'bus_id' => [$buses, fn ($bus) => 'Bus '.$bus->number.' · '.$bus->capacity.' pl.'],
                            'driver_id' => [$drivers, fn ($staff) => $staff->shortName()],
                            'conveyor_id' => [$conveyors, fn ($staff) => $staff->shortName()],
                            'device_id' => [$devices, fn ($device) => $device->label],
                        ] as $field => [$options, $label])
                            <td class="min-w-36">
                                <select wire:change="assign({{ $mission->id }}, '{{ $field }}', $event.target.value)" aria-label="{{ $field }}"
                                        class="ui-field h-9 w-full rounded-[9px] bg-field px-2.5 text-[13px] ring-1 ring-inset ring-hairline focus:ring-2 focus:ring-brand focus:outline-none">
                                    <option value="">—</option>
                                    @foreach ($options as $option)
                                        <option value="{{ $option->id }}" @selected($mission->{$field} === $option->id) wire:key="opt-{{ $mission->id }}-{{ $field }}-{{ $option->id }}">{{ $label($option) }}</option>
                                    @endforeach
                                </select>
                                @error("mission.{$mission->id}.{$field}") <p class="mt-1 text-[11px] text-danger">{{ $message }}</p> @enderror
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </x-ui.table>
        @endforeach
    @endif

    @if ($event)
        <x-ui.card>
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-[17px] font-semibold tracking-tight">Bus de réserve</h2>
                    <p class="text-[13px] text-ink-secondary">Proposés au service technique en cas de panne (ST-05).</p>
                </div>
            </div>
            <ul class="mt-4 divide-y divide-hairline">
                @forelse ($reserves as $reserve)
                    <li wire:key="reserve-{{ $reserve->id }}" class="flex items-center justify-between py-2.5">
                        <span><span class="font-medium">Bus {{ $reserve->bus->number }}</span> <span class="text-ink-secondary">· {{ $reserve->waiting_place }}</span></span>
                        <x-ui.button variant="ghost" size="sm" type="button" wire:click="removeReserve({{ $reserve->id }})">Retirer</x-ui.button>
                    </li>
                @empty
                    <li class="py-3 text-[14px] text-ink-tertiary">Aucun bus de réserve.</li>
                @endforelse
            </ul>
            <form wire:submit="addReserve" class="mt-4 grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                <x-ui.select label="Bus" name="reserveBusId" wire:model="reserveBusId" :options="$reserveOptions" placeholder="Choisir" />
                <x-ui.input label="Position d'attente" name="waitingPlace" wire:model="waitingPlace" placeholder="Parking P5 Ebimpé" />
                <x-ui.button variant="secondary">Ajouter</x-ui.button>
            </form>
        </x-ui.card>
    @endif
</div>
