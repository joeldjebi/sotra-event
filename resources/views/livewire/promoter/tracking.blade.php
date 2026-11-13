<div wire:poll.15s.visible>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div class="w-full sm:w-80">
            <x-ui.select label="Événement" name="eventId" wire:model.live="eventId" :options="$eventOptions" placeholder="Choisir un événement" />
        </div>
    </div>

    @if (! $event)
        <x-ui.card padding="p-0">
            <x-ui.empty-state icon="map" title="Aucun événement en cours"
                              description="Le suivi en temps réel des arrivées s'affichera ici dès qu'un de vos événements sera en vente." />
        </x-ui.card>
    @else
        <x-ui.card class="mb-6" padding="p-5 sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-[22px] font-semibold tracking-tight">{{ $event->name }}</h2>
                    <p class="text-[14px] text-ink-secondary">
                        {{ $event->venue->name }} · {{ ucfirst($event->starts_at->translatedFormat('l j F')) }}
                        · ouverture des portes {{ ($event->doors_open_at ?? $event->starts_at)->format('H\hi') }}
                    </p>
                </div>
                @if ($event->company->phone)
                    <x-ui.button variant="secondary" icon="phone" :href="'tel:'.preg_replace('/\s+/', '', $event->company->phone)">Appeler la coordination {{ $event->company->name }}</x-ui.button>
                @endif
            </div>
        </x-ui.card>

        <div class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
            <x-ui.kpi label="Spectateurs arrivés" :value="number_format($summary['arrived'], 0, ',', ' ')" :hint="'sur '.number_format($summary['sold'], 0, ',', ' ').' Pass vendus'" icon="users" />
            <x-ui.kpi label="Bus arrivés" :value="$summary['buses_arrived'].' / '.$summary['buses_planned']" :hint="$summary['buses_on_route'].' en route'" icon="bus" />
            <x-ui.kpi label="Prochaine arrivée" :value="$summary['next'] ? $summary['next']['at']->format('H\hi') : '—'"
                      :hint="$summary['next'] ? 'Bus '.$summary['next']['bus'].' · '.$summary['next']['point'].' · '.$summary['next']['passengers'].' passagers' : 'aucun bus en route'" icon="clock" />
            <x-ui.kpi label="Retards signalés" :value="(string) count($summary['delays'])" :tone="count($summary['delays']) > 0 ? 'danger' : 'default'"
                      :hint="count($summary['delays']) > 0 ? $summary['delays'][0]['bus'].' · '.$summary['delays'][0]['label'] : 'aucun retard'" icon="exclamation" />
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
            <x-ui.card>
                <div class="flex flex-wrap items-baseline justify-between gap-3">
                    <div>
                        <h2 class="text-[17px] font-semibold tracking-tight">Flux d'arrivée au lieu</h2>
                        <p class="text-[13px] text-ink-secondary">Spectateurs par tranche de 15 minutes</p>
                    </div>
                    <div class="flex gap-4 text-[13px] text-ink-secondary">
                        <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-ink-tertiary"></span>Prévu</span>
                        <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-brand"></span>Réel</span>
                    </div>
                </div>
                @if ($flow['labels'] === [])
                    <x-ui.empty-state icon="chart" title="Pas encore de flux" description="Le flux prévu apparaît dès que le plan de transport est généré." />
                @else
                    <x-ui.chart class="mt-4" :config="$flow" label="Flux d'arrivée prévu et réel par tranche de 15 minutes" />
                @endif
            </x-ui.card>

            <div class="space-y-6">
                <x-ui.card>
                    <h2 class="text-[17px] font-semibold tracking-tight">Retards signalés</h2>
                    <ul class="mt-3 divide-y divide-hairline">
                        @forelse ($summary['delays'] as $delay)
                            <li wire:key="delay-{{ $loop->index }}" class="py-2.5 text-[14px]">
                                <p class="font-medium text-danger">{{ $delay['bus'] }} · {{ $delay['point'] }}</p>
                                <p class="text-ink-secondary">{{ $delay['label'] }}@if ($delay['action']) · {{ $delay['action'] }}@endif</p>
                            </li>
                        @empty
                            <li class="py-3 text-[14px] text-ink-tertiary">Aucun retard signalé.</li>
                        @endforelse
                    </ul>
                </x-ui.card>
            </div>
        </div>

        <div class="mt-6">
            @include('livewire.promoter.partials.composer')
        </div>
    @endif
</div>
