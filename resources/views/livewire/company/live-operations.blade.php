<div wire:poll.10s class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div class="flex flex-wrap items-end gap-3">
            <div class="w-full sm:w-80"><x-ui.select label="Événement" name="eventId" wire:model.live="eventId" :options="$eventOptions" placeholder="Choisir un événement" /></div>
            <x-ui.segmented label="Sens">
                <x-ui.segment :active="$direction === 'aller'" wire:click="$set('direction', 'aller')">Aller</x-ui.segment>
                <x-ui.segment :active="$direction === 'retour'" wire:click="$set('direction', 'retour')">Retour</x-ui.segment>
            </x-ui.segmented>
        </div>
        @if ($event)
            <div class="flex flex-wrap gap-2">
                <x-ui.badge tone="success" class="px-3 py-1 text-[13px]">{{ $counts['en_route'] }} en route</x-ui.badge>
                <x-ui.badge tone="warning" class="px-3 py-1 text-[13px]">{{ $counts['embarquement'] }} en embarquement</x-ui.badge>
                <x-ui.badge tone="danger" class="px-3 py-1 text-[13px]">{{ $counts['panne'] }} {{ \Illuminate\Support\Str::plural('panne', $counts['panne']) }}</x-ui.badge>
                <x-ui.badge class="px-3 py-1 text-[13px]">{{ $counts['arrivee'] }} arrivés</x-ui.badge>
                <x-ui.badge class="px-3 py-1 text-[13px]">{{ $counts['reserve'] }} en réserve</x-ui.badge>
            </div>
        @endif
    </div>

    @if (! $event)
        <x-ui.card padding="p-0"><x-ui.empty-state icon="map" title="Aucun événement en cours" description="Le suivi en direct s'affiche pour les événements en vente de votre compagnie." /></x-ui.card>
    @else
        <div @class(['grid gap-6', 'xl:grid-cols-[minmax(0,1fr)_360px]' => $alerts->isNotEmpty()])>
            <x-ui.map :data="$map" :label="'Carte des bus · '.$event->name" :height="420" />

            @if ($alerts->isNotEmpty())
                <div class="space-y-4">
                    @foreach ($alerts as $alert)
                        <x-ui.card wire:key="alert-{{ $alert->id }}" padding="p-5" @class(['ring-2 !ring-danger' => $alert->urgency->value === 'urgent' && $alert->status->value === 'ouverte'])>
                            <p class="text-[12px] font-semibold tracking-wide text-danger uppercase">Panne signalée · {{ $alert->created_at->diffForHumans() }}</p>
                            <p class="mt-1 text-[19px] font-semibold tracking-tight">Bus {{ $alert->bus?->number ?? '?' }} · {{ $alert->mission->boardingPoint->commune }} → {{ $alert->mission->direction->value === 'aller' ? $event->venue->name : $alert->mission->boardingPoint->commune }}</p>
                            <p class="mt-1 text-[14px] text-ink-secondary">{{ $alert->type->label() }}@if ($alert->details) · {{ $alert->details }}@endif</p>
                            <p class="text-[14px] text-ink-secondary">{{ $alert->passengers_on_board }} passagers à bord · signalé par {{ $alert->reporter->shortName() }}@if ($alert->channel === 'sms') (SMS)@endif</p>

                            @if ($alert->techTeam)
                                <div class="mt-4 rounded-xl bg-fill px-3 py-2.5 text-[14px]">
                                    <p class="font-medium">Équipe {{ $alert->techTeam->code }} · {{ $alert->techTeam->type->label() }}</p>
                                    <p class="text-ink-secondary">{{ $alert->intervention?->number }} · {{ $alert->intervention?->step->label() }}</p>
                                </div>
                            @else
                                <div class="mt-4 flex gap-2">
                                    <select wire:model="dispatchTeam.{{ $alert->id }}" class="ui-field h-9 flex-1 rounded-[9px] bg-field px-2.5 text-[13px] ring-1 ring-inset ring-hairline">
                                        <option value="">Équipe disponible…</option>
                                        @foreach ($teamOptions as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                                    </select>
                                    <x-ui.button variant="danger" size="sm" type="button" wire:click="sendTeam({{ $alert->id }})">Envoyer l'équipe</x-ui.button>
                                </div>
                            @endif

                            @if ($alert->mission->replacedBy)
                                <p class="mt-3 rounded-xl bg-success-soft px-3 py-2.5 text-[14px] text-success">Bus {{ $alert->mission->replacedBy->bus?->number }} en remplacement · passagers prévenus.</p>
                            @else
                                <div class="mt-2 flex gap-2">
                                    <select wire:model="replacementBus.{{ $alert->id }}" class="ui-field h-9 flex-1 rounded-[9px] bg-field px-2.5 text-[13px] ring-1 ring-inset ring-hairline">
                                        <option value="">Bus de réserve…</option>
                                        @foreach ($reserveOptions as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                                    </select>
                                    <x-ui.button variant="secondary" size="sm" type="button" wire:click="replaceBus({{ $alert->id }})">Remplacer le bus</x-ui.button>
                                </div>
                            @endif
                        </x-ui.card>
                    @endforeach
                </div>
            @endif
        </div>

        <x-ui.table>
            <x-slot:head>
                <th>Bus</th>
                <th>Point</th>
                <th>Chauffeur</th>
                <th>Convoyeur</th>
                <th class="text-right">À bord</th>
                <th>Statut</th>
                <th>Position</th>
            </x-slot:head>
            @forelse ($rows as $row)
                @php($mission = $row['mission'])
                <tr wire:key="live-{{ $mission->id }}">
                    <td class="font-semibold whitespace-nowrap">{{ $mission->bus ? 'Bus '.$mission->bus->number : '—' }} <span class="text-[12px] font-normal text-ink-tertiary">{{ $mission->offer->label() }}</span></td>
                    <td>{{ $mission->boardingPoint->commune }}</td>
                    <td>{{ $mission->driver?->shortName() ?? '—' }}</td>
                    <td>{{ $mission->conveyor?->shortName() ?? '—' }}</td>
                    <td class="text-right tabular-nums">{{ $row['on_board'] }} / {{ $mission->capacity() }}</td>
                    <td><x-ui.badge :tone="$mission->status->tone()">{{ $mission->status->label() }}</x-ui.badge></td>
                    <td class="text-[13px] text-ink-secondary whitespace-nowrap">
                        @if ($row['position'])
                            {{ $row['eta'] !== null ? '≈ '.$row['eta'].' min de l\'arrivée' : 'Arrivé' }}
                            <span class="text-ink-tertiary">· {{ $row['position']->recorded_at->diffForHumans() }}</span>
                        @elseif ($mission->actual_departure_at)
                            Parti à {{ $mission->actual_departure_at->format('H\hi') }}, sans GPS
                        @else
                            Départ prévu {{ $mission->planned_departure_at->format('H\hi') }}
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7"><x-ui.empty-state icon="route" title="Aucune mission" description="Générez le plan de transport pour voir les bus ici." /></td></tr>
            @endforelse
        </x-ui.table>
    @endif
</div>
