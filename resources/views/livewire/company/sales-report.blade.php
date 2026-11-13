@php
    $money = fn (int $amount): string => $amount >= 1_000_000
        ? number_format($amount / 1_000_000, 2, ',', ' ').' M'
        : number_format($amount, 0, ',', ' ');
@endphp
<div>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div class="w-full sm:w-80">
            <x-ui.select label="Événement" name="eventId" wire:model.live="eventId" :options="$eventOptions" placeholder="Choisir un événement" />
        </div>
        @if ($event)
            <x-ui.button variant="secondary" type="button" wire:click="export" icon="document">Exporter (Excel)</x-ui.button>
        @endif
    </div>

    @if (! $event || ! $report)
        <x-ui.card padding="p-0"><x-ui.empty-state icon="chart" title="Aucune vente à afficher" description="Les ventes apparaissent dès l'ouverture de la vente d'un de vos événements." /></x-ui.card>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-ui.kpi label="Pass vendus" :value="number_format($report['sold'], 0, ',', ' ')" :hint="'sur '.number_format($report['capacity'], 0, ',', ' ').' places ouvertes'" />
            <x-ui.kpi label="Recette brute" :value="$money($report['gross'])" :hint="'FCFA, prix moyen '.number_format($report['average_price'], 0, ',', ' ')" />
            <x-ui.kpi :label="'Part compagnie ('.(100 - (float) $event->commission_rate).' %)'" :value="$money($report['company_share'])" hint="FCFA à reverser" />
            <x-ui.kpi label="Remplissage prévu" :value="$report['fill_rate'] !== null ? $report['fill_rate'].' %' : '—'"
                      :hint="$report['buses_planned'] > 0 ? $report['buses_planned'].' bus · '.$report['seats_planned'].' places' : 'plan de transport à générer'" />
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]">
            <x-ui.card>
                <h2 class="text-[17px] font-semibold tracking-tight">Répartition par offre</h2>
                <div class="mt-4 space-y-4">
                    @foreach ($report['by_offer'] as $row)
                        @php($share = $report['sold'] > 0 ? round($row['passes'] / $report['sold'] * 100) : 0)
                        <div wire:key="offer-{{ $row['offer']->value }}">
                            <div class="flex items-baseline justify-between text-[14px]">
                                <span class="font-medium">{{ $row['offer']->label() }}</span>
                                <span class="text-ink-secondary tabular-nums">{{ number_format($row['passes'], 0, ',', ' ') }} Pass · {{ $money($row['revenue']) }}</span>
                            </div>
                            <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-fill" role="progressbar" aria-valuenow="{{ $share }}" aria-valuemin="0" aria-valuemax="100">
                                <div @class(['h-full rounded-full', 'bg-ink' => $row['offer']->value === 'standard', 'bg-brand' => $row['offer']->value === 'premium', 'bg-success' => $row['offer']->value === 'gold']) style="width: {{ $share }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <p class="mt-5 border-t border-hairline pt-3 text-[13px] text-ink-secondary">
                    Canaux :
                    @foreach ($report['by_channel'] as $channel)
                        {{ $channel['channel']->label() }} {{ $channel['share'] }} %@if (! $loop->last) · @endif
                    @endforeach
                </p>
            </x-ui.card>

            <x-ui.table>
                <x-slot:head>
                    <th>Point</th>
                    @foreach ($event->activeOffers() as $offer)<th class="text-right">{{ $offer->label() }}</th>@endforeach
                    <th class="text-right">Bus</th>
                    <th class="text-right">Recette</th>
                </x-slot:head>
                @foreach ($report['by_point'] as $row)
                    <tr wire:key="point-{{ $row['point']->id }}">
                        <td><p class="font-medium">{{ $row['point']->commune }}</p><p class="text-[12px] text-ink-secondary">{{ $row['point']->place }}</p></td>
                        @foreach ($row['by_offer'] as $offer => $count)<td class="text-right tabular-nums">{{ number_format($count, 0, ',', ' ') }}</td>@endforeach
                        <td class="text-right tabular-nums">{{ $row['buses'] ?: '—' }}</td>
                        <td class="text-right font-semibold tabular-nums">{{ $money($row['revenue']) }}</td>
                    </tr>
                @endforeach
            </x-ui.table>
        </div>
    @endif
</div>
