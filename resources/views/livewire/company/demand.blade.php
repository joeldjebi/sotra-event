<div>
    @php($number = fn (int $value): string => number_format($value, 0, ',', ' '))

    <div class="mb-6 w-full sm:w-80">
        <x-ui.select label="Événement" name="eventId" wire:model.live="eventId" :options="$eventOptions" placeholder="Tous les événements" />
    </div>

    @if ($demand['rows'] === [])
        <x-ui.card padding="p-0"><x-ui.empty-state icon="map-pin" title="Aucune vente à analyser" description="La demande par commune apparaît dès les premiers Pass vendus." /></x-ui.card>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-ui.kpi label="Communes de départ" :value="count($demand['rows'])" :hint="'1re : '.$demand['rows'][0]['commune']" />
            <x-ui.kpi label="Pass vendus" :value="$number($demand['totals']['passes'])" :hint="$number($demand['totals']['outbound']).' aller · '.$number($demand['totals']['return']).' retour'" />
            <x-ui.kpi label="Recette" :value="$number($demand['totals']['revenue'])" hint="FCFA hors frais" />
            <x-ui.kpi label="Places prévues à l'aller" :value="$demand['totals']['seats'] > 0 ? $number($demand['totals']['seats']) : '—'"
                      :hint="$demand['totals']['seats'] > 0 ? round($demand['totals']['outbound'] / $demand['totals']['seats'] * 100).' % de remplissage' : 'plan de transport à générer'" />
        </div>

        <x-ui.table class="mt-6">
            <x-slot:head>
                <th>Commune</th>
                <th>Zone</th>
                <th class="text-right">Aller</th>
                <th class="text-right">Retour</th>
                @foreach ($offers as $offer)
                    <th wire:key="demand-head-{{ $offer->value }}" class="text-right">{{ $offer->label() }}</th>
                @endforeach
                <th class="text-right">Recette</th>
                <th class="text-right">Places prévues</th>
                <th class="min-w-36">Part de la demande</th>
            </x-slot:head>
            @foreach ($demand['rows'] as $row)
                <tr wire:key="demand-{{ $loop->index }}">
                    <td>
                        <p class="font-medium">{{ $row['commune'] }}</p>
                        @if ($eventId === '')
                            <p class="text-[12px] text-ink-secondary">{{ $row['events'] }} événement{{ $row['events'] > 1 ? 's' : '' }}</p>
                        @endif
                    </td>
                    <td>{{ $row['zones'] }}</td>
                    <td class="text-right tabular-nums">{{ $number($row['outbound']) }}</td>
                    <td class="text-right tabular-nums">{{ $number($row['return']) }}</td>
                    @foreach ($offers as $offer)
                        <td wire:key="demand-{{ $loop->parent->index }}-{{ $offer->value }}" class="text-right tabular-nums text-ink-secondary">{{ $number($row['by_offer'][$offer->value]) }}</td>
                    @endforeach
                    <td class="text-right tabular-nums">{{ $number($row['revenue']) }}</td>
                    <td class="text-right tabular-nums">
                        {{ $row['seats'] > 0 ? $number($row['seats']) : '—' }}
                        @if ($row['fill_rate'] !== null)
                            <span @class(['block text-[12px]', 'text-danger' => $row['fill_rate'] > 100, 'text-ink-secondary' => $row['fill_rate'] <= 100])>{{ $row['fill_rate'] }} %</span>
                        @endif
                    </td>
                    <td>
                        <div class="flex items-center gap-2">
                            <div class="h-2 flex-1 overflow-hidden rounded-full bg-fill" role="progressbar" aria-valuenow="{{ $row['share'] }}" aria-valuemin="0" aria-valuemax="100">
                                <div class="h-full rounded-full bg-brand" style="width: {{ $row['share'] }}%"></div>
                            </div>
                            <span class="w-10 text-right text-[13px] tabular-nums text-ink-secondary">{{ $row['share'] }} %</span>
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
        <p class="mt-3 text-[13px] text-ink-secondary">Places prévues : sièges des bus aller planifiés au départ de la commune. Au-delà de 100 %, des bus manquent.</p>
    @endif
</div>
