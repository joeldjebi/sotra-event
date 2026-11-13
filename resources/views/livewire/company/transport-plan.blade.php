<div class="space-y-6">
    <x-ui.alert tone="info">
        Avant l'ouverture des ventes, indiquez pour chaque offre le nombre de bus et de places par bus.
        La vente d'une offre s'arrête quand sa capacité est atteinte (RG-08). La répartition des bus par point se fera selon les ventes.
    </x-ui.alert>

    @forelse ($events as $event)
        <x-ui.card wire:key="plan-{{ $event->id }}">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-title-3">{{ $event->name }}</h2>
                    <p class="text-[13px] text-ink-secondary">{{ $event->venue->name }} · {{ $event->starts_at->translatedFormat('l j F Y, H\hi') }}
                        @if ($event->estimated_passes) · {{ number_format($event->estimated_passes, 0, ',', ' ') }} Pass estimés @endif</p>
                </div>
                <x-ui.badge :tone="$event->status->tone()">{{ $event->status->label() }}</x-ui.badge>
            </div>

            <form wire:submit="save({{ $event->id }})" class="mt-5">
                <div class="overflow-x-auto rounded-[var(--radius-card)] bg-surface shadow-[var(--shadow-card)] ring-1 ring-hairline">
                    <table class="ui-table ui-table--compact">
                        <thead>
                            <tr><th>Offre</th><th>Bus</th><th>Places par bus</th><th class="text-right">Capacité</th><th class="text-right">Flotte disponible</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($event->offers as $offer)
                                @php($available = $fleet[$offer->offer->value] ?? null)
                                <tr wire:key="offer-row-{{ $offer->id }}" class="[&>td]:!align-top">
                                    <td class="font-medium">{{ $offer->offer->label() }}</td>
                                    <td class="w-36">
                                        <input type="number" min="1" wire:model.live.debounce.400ms="capacity.{{ $offer->id }}.bus_count" class="ui-field h-10 px-3" @error("capacity.{$offer->id}.bus_count") aria-invalid="true" @enderror>
                                        @error("capacity.{$offer->id}.bus_count") <p class="mt-1 text-[12px] text-danger">{{ $message }}</p> @enderror
                                    </td>
                                    <td class="w-36">
                                        <input type="number" min="10" wire:model.live.debounce.400ms="capacity.{{ $offer->id }}.seats_per_bus" class="ui-field h-10 px-3" @error("capacity.{$offer->id}.seats_per_bus") aria-invalid="true" @enderror>
                                        @error("capacity.{$offer->id}.seats_per_bus") <p class="mt-1 text-[12px] text-danger">{{ $message }}</p> @enderror
                                    </td>
                                    <td class="text-right font-rounded text-[19px] font-semibold tabular-nums">{{ ($cap = $capacityOf($offer)) ? number_format($cap, 0, ',', ' ') : '—' }}</td>
                                    <td class="text-right text-[13px] text-ink-secondary">{{ $available ? $available->total.' bus' : 'aucun bus '.$offer->offer->label() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @error('capacity') <p class="mt-2 text-[13px] text-danger">{{ $message }}</p> @enderror
                <div class="mt-4 flex justify-end"><x-ui.button size="sm">Enregistrer la capacité</x-ui.button></div>
            </form>
        </x-ui.card>
    @empty
        <x-ui.card padding="p-0"><x-ui.empty-state icon="route" title="Aucun événement à préparer" description="Les événements proposés ou acceptés par votre direction commerciale apparaîtront ici." /></x-ui.card>
    @endforelse

    @if ($plannable->isNotEmpty())
        <section class="space-y-4">
            <div>
                <h2 class="text-title-3">Missions</h2>
                <p class="text-[13px] text-ink-secondary">Bus nécessaires = Pass vendus ÷ places par bus, arrondi au supérieur, par point, offre et sens. Au retour, chaque passager monte dans n'importe quel bus de son point et de son offre.</p>
            </div>

            @foreach ($plannable as $event)
                <x-ui.card wire:key="missions-{{ $event->id }}">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="text-[17px] font-semibold tracking-tight">{{ $event->name }}</h3>
                            <p class="text-[13px] text-ink-secondary">{{ $event->starts_at->translatedFormat('l j F Y, H\hi') }}</p>
                        </div>
                        <div class="flex gap-2">
                            <x-ui.button variant="secondary" size="sm" :href="route('company.assignments', ['evenement' => $event->id])">Affecter les bus</x-ui.button>
                            <x-ui.button size="sm" type="button" wire:click="generate({{ $event->id }})" wire:loading.attr="disabled" wire:target="generate({{ $event->id }})">
                                {{ collect($planRows[$event->id])->sum('outbound_missions') + collect($planRows[$event->id])->sum('return_missions') > 0 ? 'Actualiser les missions' : 'Générer les missions' }}
                            </x-ui.button>
                        </div>
                    </div>

                    @foreach ($planWarnings[$event->id] ?? [] as $warning)
                        <x-ui.alert tone="warning" class="mt-4" wire:key="warn-{{ $event->id }}-{{ $loop->index }}">{{ $warning }}</x-ui.alert>
                    @endforeach

                    <div class="mt-5 overflow-x-auto rounded-[var(--radius-card)] bg-surface shadow-[var(--shadow-card)] ring-1 ring-hairline">
                        <table class="ui-table ui-table--compact">
                            <thead>
                                <tr><th>Point</th><th>Offre</th><th class="text-right">Pass aller</th><th class="text-right">Bus aller</th><th class="text-right">Pass retour</th><th class="text-right">Bus retour</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($planRows[$event->id] as $row)
                                    <tr wire:key="row-{{ $event->id }}-{{ $loop->index }}">
                                        <td class="font-medium">{{ $row['point'] }}</td>
                                        <td>{{ $row['offer'] }}</td>
                                        <td class="text-right tabular-nums">{{ $row['outbound'] }}</td>
                                        <td class="text-right tabular-nums">
                                            {{ $row['outbound_missions'] }}<span class="text-ink-tertiary"> / {{ $row['outbound_needed'] }}</span>
                                        </td>
                                        <td class="text-right tabular-nums">{{ $row['return'] }}</td>
                                        <td class="text-right tabular-nums">
                                            {{ $row['return_missions'] }}<span class="text-ink-tertiary"> / {{ $row['return_needed'] }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-2 text-[12px] text-ink-tertiary">Bus : missions générées / nécessaires selon les ventes.</p>
                </x-ui.card>
            @endforeach
        </section>
    @endif
</div>
