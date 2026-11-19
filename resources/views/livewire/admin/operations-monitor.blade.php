<div>
    @php($fmt = fn (int $value): string => number_format($value, 0, ',', ' '))

    {{-- Pannes ouvertes, tous événements et toutes compagnies --}}
    <div class="mb-6" wire:poll.30s>
        <h2 class="mb-3 text-[17px] font-semibold tracking-tight">Pannes en cours · {{ $openAlerts->count() }}</h2>
        @if ($openAlerts->isEmpty())
            <x-ui.alert tone="success">Aucune panne en cours.</x-ui.alert>
        @else
            <x-ui.table>
                <x-slot:head><th>Signalée</th><th>Événement</th><th>Bus · point</th><th>Panne</th><th class="text-right">À bord</th><th>Statut</th></x-slot:head>
                @foreach ($openAlerts as $alert)
                    <tr wire:key="open-alert-{{ $alert->id }}" class="cursor-pointer hover:bg-fill-subtle" wire:click="$set('eventId', {{ $alert->event_id }})">
                        <td class="whitespace-nowrap">{{ $alert->created_at->translatedFormat('j M · H\hi') }} <span class="text-[12px] text-ink-secondary">({{ $alert->created_at->diffForHumans() }})</span></td>
                        <td>{{ $alert->event->name }}</td>
                        <td>{{ $alert->bus ? 'Bus '.$alert->bus->number : '—' }} <span class="text-[12px] text-ink-secondary">· {{ $alert->mission->boardingPoint->commune }}</span></td>
                        <td>{{ $alert->type->label() }} @if ($alert->urgency === \App\Enums\BreakdownUrgency::Urgent)<x-ui.badge tone="danger" class="ml-1">Urgent</x-ui.badge>@endif</td>
                        <td class="text-right tabular-nums">{{ $fmt((int) $alert->passengers_on_board) }}</td>
                        <td><x-ui.badge :tone="$alert->status->tone()">{{ $alert->status->label() }}</x-ui.badge></td>
                    </tr>
                @endforeach
            </x-ui.table>
        @endif
    </div>

    <div class="mb-5 flex flex-wrap items-end gap-3">
        <div class="w-full sm:w-96">
            <x-ui.select label="Événement" name="eventId" wire:model.live="eventId" :options="$eventOptions" placeholder="Choisir un événement" />
        </div>
        <x-ui.segmented label="Vue">
            @foreach ($tabs as $key => $label)
                <x-ui.segment wire:key="monitor-tab-{{ $key }}" :active="$currentTab === $key" wire:click="$set('tab', '{{ $key }}')">{{ $label }}</x-ui.segment>
            @endforeach
        </x-ui.segmented>
    </div>

    @if (! $event)
        <x-ui.card padding="p-0"><x-ui.empty-state icon="map" title="Aucun événement" description="La supervision s'ouvre dès qu'un événement est en vente." /></x-ui.card>
    @else
        <p class="mb-4 text-[14px] text-ink-secondary">{{ ucfirst($event->starts_at->translatedFormat('l j F Y · H\hi')) }} · {{ $event->venue->name }} · {{ $event->company->name }}</p>
        @switch($currentTab)
            @case('transport') @include('partials.event-tabs.transport') @break
            @case('incidents') @include('partials.event-tabs.incidents') @break
            @default @include('partials.event-tabs.carte')
        @endswitch
    @endif
</div>
