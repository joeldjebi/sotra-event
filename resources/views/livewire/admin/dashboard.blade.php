@use('App\Support\Amount')
@use('App\Enums\EventStatus')
@php
    /** @var \App\Services\Reporting\PlatformMetrics $metrics */
    $current = $summary['current'];
    $changes = $summary['changes'];
    $periodLabel = $metrics->from->translatedFormat('j M').' – '.$metrics->now->translatedFormat('j M Y');
    $split = [
        ['label' => 'Part des compagnies', 'value' => $current['company_share'], 'color' => 'chart-1', 'hint' => 'à reverser'],
        ['label' => 'Commission ACCES EVENT', 'value' => $current['commission'], 'color' => 'chart-2', 'hint' => null],
        ['label' => 'Frais de service', 'value' => $current['fees'], 'color' => 'chart-3', 'hint' => 'payés par le client'],
    ];
    $fullOffers = collect($fill)->flatMap(fn ($row) => collect($row['offers'])
        ->filter(fn ($offer) => $offer['capacity'] !== null && $offer['sold'] >= $offer['capacity'])
        ->map(fn ($offer) => ['event' => $row['event'], 'offer' => $offer['offer']]));
    $pipelineMax = max(1, ...array_column($pipeline['stages'], 'count'));
    $hasAlerts = $alerts['refund_count'] > 0 || $alerts['stale_payments'] > 0 || $fullOffers->isNotEmpty();
@endphp

<div class="space-y-6">
    {{-- Filtres : une seule rangée au-dessus de tout ce qu'ils filtrent. --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-3">
            <x-ui.segmented label="Période">
                @foreach ($periods as $days)
                    <x-ui.segment :active="$period === $days" wire:click="setPeriod({{ $days }})" wire:key="period-{{ $days }}">{{ $days }} jours</x-ui.segment>
                @endforeach
            </x-ui.segmented>
            <div class="w-56">
                <x-ui.select label="" name="company" wire:model.live="company" :options="$companies" placeholder="Toutes les compagnies" class="!h-9 !rounded-[9px] !text-[13px]" />
            </div>
        </div>
        <p class="text-footnote text-ink-tertiary">
            <span class="tabular-nums">{{ $periodLabel }}</span>
            <span wire:loading.delay class="ml-1 text-ink-secondary">· mise à jour…</span>
        </p>
    </div>

    <div class="space-y-6 transition-opacity duration-200" wire:loading.class.delay="opacity-60">
        {{-- Chiffre héros + indicateurs --}}
        <div class="grid gap-4 xl:grid-cols-12">
            <x-ui.card class="xl:col-span-5" padding="p-5 sm:p-6">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-footnote font-semibold text-ink-secondary">Chiffre d'affaires encaissé</p>
                        <p class="mt-2 flex items-baseline gap-2 font-rounded leading-none font-semibold tracking-[-0.03em]">
                            <span class="text-[44px] sm:text-[52px]">{{ Amount::number($current['revenue']) }}</span>
                            <span class="text-title-3 text-ink-secondary">FCFA</span>
                        </p>
                        <x-ui.delta class="mt-3" :change="$changes['revenue']" />
                    </div>
                    <x-ui.sparkline class="mt-1 hidden sm:block" :values="array_column($daily, 'revenue')" label="Tendance du chiffre d'affaires" />
                </div>

                <x-ui.split-bar class="mt-6" :segments="$split" label="Répartition du chiffre d'affaires" />
                <ul class="mt-4 space-y-2.5">
                    @foreach ($split as $part)
                        <li class="flex items-center gap-3 text-subheadline" wire:key="split-{{ $loop->index }}">
                            <span class="size-2.5 shrink-0 rounded-[3px]" style="background-color: var(--color-{{ $part['color'] }})" aria-hidden="true"></span>
                            <span class="min-w-0 flex-1 truncate">{{ $part['label'] }} @if ($part['hint'])<span class="text-ink-tertiary">· {{ $part['hint'] }}</span>@endif</span>
                            <span class="font-semibold tabular-nums">{{ Amount::fcfa($part['value']) }}</span>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-4 text-footnote text-ink-tertiary">TTC, commandes payées sur la période ({{ Amount::number($current['orders']) }} commandes). Les paiements à rembourser n'y figurent pas.</p>
            </x-ui.card>

            <div class="grid grid-cols-2 gap-4 xl:col-span-7">
                <x-ui.card padding="p-4 sm:p-5" class="flex flex-col">
                    <p class="text-footnote font-semibold text-ink-secondary">Pass vendus</p>
                    <div class="mt-3 flex items-end justify-between gap-3">
                        <p class="font-rounded text-[30px] leading-none font-semibold tracking-[-0.02em] sm:text-[34px]">{{ Amount::number($current['passes']) }}</p>
                        <x-ui.sparkline class="hidden sm:block" :values="array_column($daily, 'passes')" label="Tendance des Pass vendus" :width="96" :height="28" />
                    </div>
                    <x-ui.delta class="mt-auto pt-3" :change="$changes['passes']" />
                </x-ui.card>

                <x-ui.card padding="p-4 sm:p-5" class="flex flex-col">
                    <p class="text-footnote font-semibold text-ink-secondary">Spectateurs acheteurs</p>
                    <p class="mt-3 font-rounded text-[30px] leading-none font-semibold tracking-[-0.02em] sm:text-[34px]">{{ Amount::number($current['customers']) }}</p>
                    <p class="mt-2 text-footnote text-ink-tertiary">
                        Panier moyen {{ $current['orders'] > 0 ? Amount::fcfa(intdiv($current['revenue'], $current['orders'])) : '—' }}
                    </p>
                    <x-ui.delta class="mt-auto pt-3" :change="$changes['customers']" />
                </x-ui.card>

                <x-ui.card padding="p-4 sm:p-5" class="flex flex-col">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-footnote font-semibold text-ink-secondary">Événements en vente</p>
                        <span class="grid size-7 shrink-0 place-items-center rounded-full bg-brand-soft text-brand-text"><x-ui.icon name="calendar" class="size-4" /></span>
                    </div>
                    <p class="mt-3 font-rounded text-[30px] leading-none font-semibold tracking-[-0.02em] sm:text-[34px]">{{ $snapshot['events_on_sale'] }}</p>
                    <p class="mt-auto pt-3 text-footnote text-ink-tertiary">{{ $snapshot['events_upcoming'] }} à venir, toutes étapes confondues</p>
                </x-ui.card>

                <x-ui.card padding="p-4 sm:p-5" class="flex flex-col">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-footnote font-semibold text-ink-secondary">En attente de paiement</p>
                        <span class="grid size-7 shrink-0 place-items-center rounded-full bg-fill text-ink-secondary"><x-ui.icon name="clock" class="size-4" /></span>
                    </div>
                    <p class="mt-3 font-rounded text-[30px] leading-none font-semibold tracking-[-0.02em] sm:text-[34px]">{{ $snapshot['pending_orders'] }}</p>
                    <p class="mt-auto pt-3 text-footnote text-ink-tertiary">
                        {{ $snapshot['pending_orders'] > 0 ? Amount::fcfa($snapshot['pending_amount']).' de places bloquées' : 'Aucune place bloquée en ce moment' }}
                    </p>
                </x-ui.card>
            </div>
        </div>

        {{-- Ventes par jour + points d'attention --}}
        <div class="grid gap-4 xl:grid-cols-12">
            <x-ui.card class="min-w-0 xl:col-span-8" padding="p-5 sm:p-6">
                <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                    <div>
                        <h2 class="text-headline">Pass vendus par jour</h2>
                        <p class="text-footnote text-ink-secondary">Par date d'encaissement · montant encaissé dans l'info-bulle</p>
                    </div>
                    @php $peak = collect($daily)->sortByDesc('passes')->first(); @endphp
                    @if ($peak && $peak['passes'] > 0)
                        <p class="text-footnote text-ink-secondary">Record : <span class="font-semibold text-ink tabular-nums">{{ Amount::number($peak['passes']) }} Pass</span> le {{ $peak['date']->translatedFormat('j F') }}</p>
                    @endif
                </div>

                @if ($current['passes'] > 0)
                    <x-ui.chart class="mt-5" wire:key="chart-daily" :config="$dailyChart" :height="248"
                                label="Pass vendus par jour du {{ $periodLabel }}, {{ Amount::number($current['passes']) }} au total" />
                    <details class="group mt-3">
                        <summary class="inline-flex cursor-pointer list-none items-center gap-1 text-footnote font-medium text-brand-text">
                            <x-ui.icon name="chevron-right" class="size-3.5 transition-transform group-open:rotate-90" /> Données du graphique
                        </summary>
                        <div class="mt-2 max-h-64 overflow-y-auto rounded-[12px] ring-1 ring-hairline">
                            <table class="ui-table ui-table--compact">
                                <thead><tr><th>Jour</th><th class="text-right">Pass</th><th class="text-right">Encaissé</th></tr></thead>
                                <tbody>
                                    @foreach (array_reverse($daily) as $day)
                                        <tr wire:key="daily-row-{{ $day['date']->toDateString() }}">
                                            <td>{{ ucfirst($day['date']->translatedFormat('D j M')) }}</td>
                                            <td class="text-right tabular-nums">{{ Amount::number($day['passes']) }}</td>
                                            <td class="text-right tabular-nums">{{ Amount::fcfa($day['revenue']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                @else
                    <x-ui.empty-state icon="chart" title="Aucune vente sur la période" description="Les ventes encaissées apparaîtront ici jour par jour." />
                @endif
            </x-ui.card>

            <x-ui.card class="xl:col-span-4" padding="p-5 sm:p-6">
                <h2 class="text-headline">À surveiller</h2>
                <p class="text-footnote text-ink-secondary">Paiements et capacité, en ce moment</p>

                @if (! $hasAlerts)
                    <div class="mt-6 flex items-center gap-3 rounded-[14px] bg-success-soft p-4 text-success">
                        <x-ui.icon name="check-circle" class="size-5 shrink-0" />
                        <p class="text-subheadline font-medium">Rien à signaler</p>
                    </div>
                @else
                    <ul class="mt-4 space-y-3">
                        @if ($alerts['refund_count'] > 0)
                            <li class="rounded-[14px] bg-danger-soft p-3.5">
                                <div class="flex items-start gap-3">
                                    <x-ui.icon name="exclamation" class="mt-0.5 size-[18px] shrink-0 text-danger" />
                                    <div class="min-w-0 flex-1">
                                        <p class="text-subheadline font-semibold text-danger">{{ $alerts['refund_count'] }} {{ Str::plural('commande', $alerts['refund_count']) }} à rembourser</p>
                                        <p class="text-footnote text-ink-secondary">{{ Amount::fcfa($alerts['refund_amount']) }} payés après la libération des places</p>
                                        <ul class="mt-2 space-y-1">
                                            @foreach ($alerts['refund_orders'] as $order)
                                                <li class="flex items-center justify-between gap-2 text-footnote" wire:key="refund-{{ $order->id }}">
                                                    <span class="truncate"><span class="font-mono text-[12px]">{{ $order->reference }}</span> · {{ $order->offer->label() }}</span>
                                                    <span class="shrink-0 tabular-nums">{{ Amount::fcfa($order->total) }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                            </li>
                        @endif
                        @if ($alerts['stale_payments'] > 0)
                            <li class="flex items-start gap-3 rounded-[14px] bg-warning-soft p-3.5">
                                <x-ui.icon name="clock" class="mt-0.5 size-[18px] shrink-0 text-warning" />
                                <div>
                                    <p class="text-subheadline font-semibold text-warning">{{ $alerts['stale_payments'] }} {{ Str::plural('paiement', $alerts['stale_payments']) }} sans confirmation</p>
                                    <p class="text-footnote text-ink-secondary">Depuis plus de {{ \App\Services\Reporting\PlatformMetrics::STALE_PAYMENT_MINUTES }} min · {{ Amount::fcfa($alerts['stale_amount']) }} à réconcilier avec l'agrégateur</p>
                                </div>
                            </li>
                        @endif
                        @foreach ($fullOffers as $full)
                            <li class="flex items-start gap-3 rounded-[14px] bg-info-soft p-3.5" wire:key="full-{{ $full['event']->id }}-{{ $full['offer']->value }}">
                                <x-ui.icon name="info" class="mt-0.5 size-[18px] shrink-0 text-info" />
                                <div class="min-w-0">
                                    <p class="text-subheadline font-semibold text-info">Offre {{ $full['offer']->label() }} complète</p>
                                    <p class="truncate text-footnote text-ink-secondary">{{ $full['event']->name }} · ajouter des bus avec la compagnie ?</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        </div>

        {{-- Remplissage + répartitions --}}
        <div class="grid gap-4 xl:grid-cols-12">
            <x-ui.card class="xl:col-span-7" padding="p-5 sm:p-6">
                <h2 class="text-headline">Remplissage des événements en vente</h2>
                <p class="text-footnote text-ink-secondary">Places vendues / capacité (bus × places), segment le plus chargé</p>

                @forelse ($fill as $row)
                    <div class="mt-5 border-t border-separator pt-5 first-of-type:border-0 first-of-type:pt-0" wire:key="fill-{{ $row['event']->id }}">
                        <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                            <div class="min-w-0">
                                <a href="{{ route('admin.events.edit', $row['event']) }}" class="font-semibold hover:text-brand-text">{{ $row['event']->name }}</a>
                                <p class="text-footnote text-ink-secondary">{{ ucfirst($row['event']->starts_at->translatedFormat('l j F · H\hi')) }} · {{ $row['event']->company->name }}</p>
                            </div>
                            <p class="text-subheadline tabular-nums">
                                <span class="font-semibold">{{ Amount::number($row['sold']) }}</span><span class="text-ink-secondary"> / {{ Amount::number($row['capacity']) }} places</span>
                                @if ($row['ratio'] !== null)<span class="ml-1 font-semibold">{{ Amount::percent($row['ratio']) }}</span>@endif
                            </p>
                        </div>
                        <div class="mt-3 h-2.5 w-full overflow-hidden rounded-full" style="background-color: var(--color-chart-track)"
                             role="meter" aria-valuemin="0" aria-valuemax="{{ $row['capacity'] }}" aria-valuenow="{{ $row['sold'] }}" aria-label="Remplissage de {{ $row['event']->name }}">
                            <div class="h-full rounded-full bg-brand" style="width: {{ round(($row['ratio'] ?? 0) * 100, 1) }}%"></div>
                        </div>
                        <ul class="mt-4 grid gap-3 sm:grid-cols-3">
                            @foreach ($row['offers'] as $offer)
                                @php $ratio = $offer['capacity'] ? min(1, $offer['sold'] / $offer['capacity']) : null; @endphp
                                <li class="min-w-0" wire:key="fill-{{ $row['event']->id }}-{{ $offer['offer']->value }}">
                                    <div class="flex items-center justify-between gap-2 text-footnote">
                                        <span class="font-medium">{{ $offer['offer']->label() }}</span>
                                        @if ($ratio !== null && $ratio >= 1)
                                            <x-ui.badge tone="info">Complet</x-ui.badge>
                                        @elseif ($ratio !== null && $ratio >= 0.9)
                                            <x-ui.badge tone="warning">Presque complet</x-ui.badge>
                                        @endif
                                    </div>
                                    <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full" style="background-color: var(--color-chart-track)">
                                        <div class="h-full rounded-full bg-brand" style="width: {{ round(($ratio ?? 0) * 100, 1) }}%"></div>
                                    </div>
                                    <p class="mt-1 text-footnote text-ink-secondary tabular-nums">
                                        {{ Amount::number($offer['sold']) }} / {{ $offer['capacity'] !== null ? Amount::number($offer['capacity']) : 'capacité à définir' }}
                                    </p>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @empty
                    <x-ui.empty-state icon="ticket" title="Aucun événement en vente" description="Le remplissage s'affiche dès qu'un événement accepté par sa compagnie est mis en vente." />
                @endforelse
            </x-ui.card>

            <x-ui.card class="xl:col-span-5" padding="p-5 sm:p-6">
                <h2 class="text-headline">Répartition des ventes</h2>
                <p class="text-footnote text-ink-secondary">Pass vendus sur la période</p>

                @foreach ([['Par offre', $offers, $offerColors], ['Par canal', $channels, $channelColors]] as [$title, $rows, $colors])
                    <div class="mt-5" wire:key="breakdown-{{ $loop->index }}">
                        <h3 class="text-footnote font-semibold text-ink-secondary">{{ $title }}</h3>
                        <x-ui.split-bar class="mt-2.5" :label="'Pass vendus '.Str::lower($title)"
                                        :segments="array_map(fn ($row) => ['label' => $row['label'], 'value' => $row['passes'], 'color' => $colors[$row['key']]], $rows)" />
                        <ul class="mt-3 space-y-2">
                            @foreach ($rows as $row)
                                <li class="flex items-center gap-3 text-subheadline" wire:key="breakdown-{{ $loop->parent->index }}-{{ $row['key'] }}">
                                    <span class="size-2.5 shrink-0 rounded-[3px]" style="background-color: var(--color-{{ $colors[$row['key']] }})" aria-hidden="true"></span>
                                    <span class="min-w-0 flex-1 truncate">{{ $row['label'] }}</span>
                                    <span class="w-14 text-right text-ink-secondary tabular-nums">{{ Amount::percent($row['share']) }}</span>
                                    <span class="w-16 text-right font-semibold tabular-nums">{{ Amount::number($row['passes']) }}</span>
                                    <span class="hidden w-24 text-right text-ink-secondary tabular-nums sm:inline">{{ Amount::compact($row['revenue']) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </x-ui.card>
        </div>

        {{-- Communes, pipeline, jour J --}}
        <div class="grid gap-4 lg:grid-cols-2 xl:grid-cols-12">
            <x-ui.card class="min-w-0 xl:col-span-4" padding="p-5 sm:p-6">
                <h2 class="text-headline">Communes de départ</h2>
                <p class="text-footnote text-ink-secondary">Pass vendus sur la période, les {{ count($communes) }} premières</p>
                @if ($communes !== [])
                    <x-ui.chart class="mt-4" wire:key="chart-communes" :config="$communesChart" :height="max(140, count($communes) * 34 + 30)"
                                label="Pass vendus par commune de départ : {{ collect($communes)->map(fn ($c) => $c['commune'].' '.$c['passes'])->implode(', ') }}" />
                @else
                    <x-ui.empty-state icon="map-pin" title="Aucun départ" description="Les communes les plus demandées s'afficheront ici." />
                @endif
            </x-ui.card>

            <x-ui.card class="xl:col-span-4" padding="p-5 sm:p-6">
                <h2 class="text-headline">Pipeline des événements</h2>
                <p class="text-footnote text-ink-secondary">Nombre d'événements par étape, aujourd'hui</p>
                <ol class="mt-5 space-y-3">
                    @foreach ($pipeline['stages'] as $stage)
                        <li class="grid grid-cols-[minmax(0,8.5rem)_1fr_2rem] items-center gap-3" wire:key="stage-{{ $stage['status']->value }}">
                            <span class="truncate text-footnote">{{ $stage['status'] === EventStatus::Proposed ? 'Proposé' : $stage['status']->label() }}</span>
                            <span class="block h-3.5 w-full">
                                @if ($stage['count'] > 0)
                                    <span class="block h-full rounded-r-[4px]" style="width: {{ max(4, round($stage['count'] / $pipelineMax * 100)) }}%; background-color: var(--color-chart-step-{{ $loop->iteration }})"></span>
                                @else
                                    <span class="block h-full w-px bg-line"></span>
                                @endif
                            </span>
                            <span class="text-right text-subheadline font-semibold tabular-nums">{{ $stage['count'] }}</span>
                        </li>
                    @endforeach
                </ol>
                <p class="mt-5 border-t border-separator pt-3 text-footnote text-ink-secondary">
                    @foreach ($pipeline['others'] as $other)
                        {{ $other['status']->label() }} <span class="font-semibold text-ink tabular-nums">{{ $other['count'] }}</span>@if (! $loop->last)<span class="mx-1.5 text-ink-tertiary">·</span>@endif
                    @endforeach
                </p>
            </x-ui.card>

            <x-ui.card class="lg:col-span-2 xl:col-span-4" padding="p-5 sm:p-6">
                <h2 class="text-headline">Jour J</h2>
                <p class="text-footnote text-ink-secondary">Exploitation en temps réel</p>
                <ul class="mt-4 divide-y divide-separator">
                    @foreach ([
                        ['bus', 'Bus en route', $dayOf['on_route'] + $dayOf['boarding'] > 0 ? "{$dayOf['on_route']} en route · {$dayOf['boarding']} en embarquement" : 'Aucun bus en circulation', false],
                        ['wrench', 'Alertes panne', $dayOf['open_alerts'] > 0 ? "{$dayOf['open_alerts']} en cours · dont {$dayOf['urgent_alerts']} urgente(s)" : 'Aucune panne en cours', $dayOf['urgent_alerts'] > 0],
                        ['chat', 'Réclamations', 'Disponible avec l\'espace qualité', false],
                    ] as [$icon, $label, $detail, $critical])
                        <li class="flex items-center gap-3 py-3 first:pt-0 last:pb-0">
                            <span @class(['grid size-8 shrink-0 place-items-center rounded-[10px]', 'bg-danger-soft text-danger' => $critical, 'bg-fill text-ink-tertiary' => ! $critical])><x-ui.icon :name="$icon" class="size-[18px]" /></span>
                            <div class="min-w-0">
                                <p class="text-subheadline font-medium">{{ $label }}</p>
                                <p @class(['text-footnote', 'text-danger' => $critical, 'text-ink-tertiary' => ! $critical])>{{ $detail }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
        </div>

        {{-- Événements à venir --}}
        <section>
            <div class="mb-3 flex items-baseline justify-between gap-3 px-1">
                <h2 class="text-headline">Événements à venir</h2>
                <a href="{{ route('admin.events') }}" class="text-footnote font-medium text-brand-text hover:underline">Tous les événements</a>
            </div>
            <x-ui.table>
                <x-slot:head>
                    <th class="min-w-56">Événement</th>
                    <th>Date</th>
                    <th>Compagnie</th>
                    <th>Statut</th>
                    <th class="min-w-48">Ventes</th>
                </x-slot:head>
                @forelse ($upcoming as $row)
                    <tr wire:key="upcoming-{{ $row['event']->id }}">
                        <td>
                            <a href="{{ route('admin.events.edit', $row['event']) }}" class="font-medium hover:text-brand-text">{{ $row['event']->name }}</a>
                            <p class="text-[13px] text-ink-secondary">{{ $row['event']->venue->name }}</p>
                        </td>
                        <td class="whitespace-nowrap">
                            <p>{{ ucfirst($row['event']->starts_at->translatedFormat('D j M')) }}</p>
                            <p class="text-[13px] text-ink-secondary">{{ $row['event']->starts_at->diffForHumans(['parts' => 1]) }}</p>
                        </td>
                        <td>{{ $row['event']->company->name }}</td>
                        <td><x-ui.badge :tone="$row['event']->status->tone()">{{ $row['event']->status->label() }}</x-ui.badge></td>
                        <td>
                            @if ($row['event']->status === EventStatus::OnSale && $row['capacity'] > 0)
                                <div class="flex items-center gap-3">
                                    <div class="h-1.5 w-24 shrink-0 overflow-hidden rounded-full" style="background-color: var(--color-chart-track)">
                                        <div class="h-full rounded-full bg-brand" style="width: {{ round(($row['ratio'] ?? 0) * 100, 1) }}%"></div>
                                    </div>
                                    <span class="text-[13px] whitespace-nowrap tabular-nums"><span class="font-semibold">{{ Amount::number($row['sold']) }}</span> / {{ Amount::number($row['capacity']) }}</span>
                                </div>
                            @else
                                <span class="text-[13px] text-ink-tertiary">{{ $row['capacity'] > 0 ? Amount::number($row['capacity']).' places · vente non ouverte' : 'Capacité à définir' }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5"><x-ui.empty-state icon="calendar" title="Aucun événement à venir" description="Les événements proposés, acceptés ou en vente apparaîtront ici." /></td></tr>
                @endforelse
            </x-ui.table>
        </section>

        {{-- Activité récente --}}
        <section>
            <div class="mb-3 px-1">
                <h2 class="text-headline">Dernières commandes payées</h2>
            </div>
            <x-ui.table compact>
                <x-slot:head>
                    <th>Commande</th>
                    <th>Événement</th>
                    <th>Départ</th>
                    <th>Offre</th>
                    <th class="text-right">Pass</th>
                    <th class="text-right">Montant</th>
                    <th>Canal</th>
                    <th class="text-right">Payée</th>
                </x-slot:head>
                @forelse ($recent as $order)
                    <tr wire:key="recent-{{ $order->id }}">
                        <td class="font-mono text-[12px] whitespace-nowrap">{{ $order->reference }}</td>
                        <td class="max-w-56 truncate">{{ $order->event->name }}</td>
                        <td>{{ $order->boardingPoint->commune }}</td>
                        <td>{{ $order->offer->label() }}</td>
                        <td class="text-right tabular-nums">{{ $order->quantity }}</td>
                        <td class="text-right whitespace-nowrap tabular-nums">{{ Amount::fcfa($order->total) }}</td>
                        <td><x-ui.badge>{{ $order->channel === \App\Enums\SalesChannel::App ? 'Application' : $order->channel->label() }}</x-ui.badge></td>
                        <td class="text-right whitespace-nowrap text-ink-secondary">{{ $order->paid_at?->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8"><x-ui.empty-state icon="ticket" title="Aucune commande payée" description="Les ventes apparaîtront ici en temps réel." /></td></tr>
                @endforelse
            </x-ui.table>
        </section>
    </div>
</div>
