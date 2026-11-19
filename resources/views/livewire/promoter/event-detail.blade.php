<div>
    @php($fmt = fn (int $value): string => number_format($value, 0, ',', ' '))

    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <a href="{{ route('promoter.events') }}" class="text-[14px] font-medium text-brand-text hover:underline">‹ Mes événements</a>
            <div class="mt-1 flex flex-wrap items-center gap-2">
                <h2 class="text-[22px] font-semibold tracking-tight">{{ $event->name }}</h2>
                <x-ui.badge :tone="$event->status->tone()">{{ $event->status->label() }}</x-ui.badge>
            </div>
            <p class="text-[14px] text-ink-secondary">{{ ucfirst($event->starts_at->translatedFormat('l j F Y · H\hi')) }} · {{ $event->venue->name }} · {{ $event->company->name }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <x-ui.button variant="secondary" size="sm" :href="route('promoter.dashboard', ['evenement' => $event->id])">Suivi du jour J</x-ui.button>
            @can('promo-kit.view')
                <x-ui.button variant="secondary" size="sm" :href="route('promoter.kit')">Kit de promotion</x-ui.button>
            @endcan
            <x-ui.button variant="secondary" size="sm" :href="route('promoter.events.export', $event->id)">Exporter (Excel)</x-ui.button>
            <x-ui.button variant="secondary" size="sm" :href="route('promoter.report.pdf', $event)">Bilan PDF</x-ui.button>
        </div>
    </div>

    <x-ui.segmented label="Onglets" class="mb-6 !flex max-w-full overflow-x-auto whitespace-nowrap sm:!inline-flex">
        @foreach ($tabs as $key => $label)
            <x-ui.segment wire:key="tab-{{ $key }}" :active="$tab === $key" wire:click="$set('tab', '{{ $key }}')">{{ $label }}</x-ui.segment>
        @endforeach
    </x-ui.segmented>

    {{-- Indicateurs communs à tous les onglets --}}
    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.kpi label="Pass vendus" :value="$fmt($summary['sold'])" :hint="'sur '.$fmt($summary['capacity']).' places ouvertes'" icon="ticket" />
        <x-ui.kpi label="Remplissage" :value="$summary['fill_rate'] === null ? '—' : $summary['fill_rate'].' %'" :hint="$summary['capacity'] > 0 ? '' : 'Capacité à définir par la compagnie'" icon="chart" />
        <x-ui.kpi label="Places restantes" :value="$fmt($summary['remaining'])" icon="users" />
        <x-ui.kpi label="Bus prévus (aller)" :value="$fmt($summary['buses_planned'])" hint="Selon les ventes, par point et par offre" icon="bus" />
    </div>

    @switch($tab)
        @case('ventes')
            <div class="grid gap-6 xl:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
                <x-ui.card>
                    <h3 class="text-[17px] font-semibold tracking-tight">Pass vendus par jour</h3>
                    @if ($sales['daily']['labels'] === [])
                        <p class="mt-3 text-[14px] text-ink-secondary">La vente n'est pas encore ouverte.</p>
                    @else
                        <x-ui.chart class="mt-4" wire:key="chart-daily-{{ $event->id }}" :config="$sales['daily']" label="Pass vendus par jour" />
                    @endif
                </x-ui.card>
                <div class="space-y-6">
                    <x-ui.table>
                        <x-slot:head><th>Offre</th><th class="text-right">Vendus</th><th class="text-right">Capacité</th><th class="text-right">Restants</th></x-slot:head>
                        @foreach ($sales['by_offer'] as $row)
                            <tr wire:key="offer-{{ $row['offer']->value }}">
                                <td class="font-medium">{{ $row['offer']->label() }}</td>
                                <td class="text-right tabular-nums">{{ $fmt($row['passes']) }}</td>
                                <td class="text-right tabular-nums">{{ $fmt($row['capacity']) }}</td>
                                <td class="text-right tabular-nums">{{ $fmt($row['remaining']) }}</td>
                            </tr>
                        @endforeach
                    </x-ui.table>
                    <x-ui.table>
                        <x-slot:head><th>Type de Pass</th><th class="text-right">Vendus</th></x-slot:head>
                        @foreach ($sales['by_type'] as $row)
                            <tr wire:key="type-{{ $row['type']->value }}"><td>{{ $row['type']->label() }}</td><td class="text-right tabular-nums">{{ $fmt($row['passes']) }}</td></tr>
                        @endforeach
                    </x-ui.table>
                    <x-ui.table>
                        <x-slot:head><th>Canal de vente</th><th class="text-right">Pass</th><th class="text-right">Part</th></x-slot:head>
                        @foreach ($sales['by_channel'] as $row)
                            <tr wire:key="channel-{{ $loop->index }}"><td>{{ $row['label'] }}</td><td class="text-right tabular-nums">{{ $fmt($row['passes']) }}</td><td class="text-right tabular-nums">{{ $row['share'] }} %</td></tr>
                        @endforeach
                    </x-ui.table>
                </div>
            </div>
            @break

        @case('points')
            <x-ui.table>
                <x-slot:head>
                    <th>Point d'embarquement</th>
                    <th>Zone</th>
                    <th>Départ aller · retour</th>
                    @foreach ($event->activeOffers() as $offer)
                        <th class="text-right" wire:key="head-{{ $offer->value }}">{{ $offer->label() }}</th>
                    @endforeach
                    <th class="text-right">Pass vendus</th>
                    <th class="text-right">Bus prévus</th>
                    <th class="text-right">Montés aller · retour</th>
                </x-slot:head>
                @forelse ($points as $row)
                    <tr wire:key="point-{{ $row['point']->id }}">
                        <td><p class="font-medium">{{ $row['point']->commune }}</p><p class="text-[12px] text-ink-secondary">{{ $row['point']->place }}</p></td>
                        <td>Zone {{ $row['point']->zone }}</td>
                        <td class="tabular-nums">{{ $row['point']->departure_at->format('H\hi') }} · {{ $row['point']->return_departure_at->format('H\hi') }}</td>
                        @foreach ($event->activeOffers() as $offer)
                            <td class="text-right tabular-nums" wire:key="cell-{{ $row['point']->id }}-{{ $offer->value }}">{{ $fmt($row['by_offer'][$offer->value] ?? 0) }}</td>
                        @endforeach
                        <td class="text-right font-medium tabular-nums">{{ $fmt($row['passes']) }}</td>
                        <td class="text-right tabular-nums">{{ $fmt($row['buses']) }}</td>
                        <td class="text-right tabular-nums">{{ $fmt($row['boarded_outbound']) }} · {{ $fmt($row['boarded_return']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8"><x-ui.empty-state icon="map-pin" title="Aucun point d'embarquement" description="ACCES EVENT les définit avec la compagnie." /></td></tr>
                @endforelse
            </x-ui.table>
            @break

        @case('embarquement')
            <div class="grid gap-6 lg:grid-cols-2">
                @foreach (['outbound' => 'Aller', 'return' => 'Retour'] as $key => $label)
                    @php($segment = $boarding[$key])
                    <x-ui.card wire:key="segment-{{ $key }}">
                        <h3 class="text-[17px] font-semibold tracking-tight">{{ $label }}</h3>
                        <p class="mt-2 text-[30px] font-semibold tabular-nums">{{ $fmt($segment['boarded']) }} <span class="text-[17px] font-normal text-ink-secondary">/ {{ $fmt($segment['expected']) }} Pass</span></p>
                        <div class="mt-3 h-2 overflow-hidden rounded-full bg-fill"><div class="h-full rounded-full bg-brand" style="width: {{ min(100, $segment['rate'] ?? 0) }}%"></div></div>
                        <p class="mt-2 text-[14px] text-ink-secondary">
                            {{ $segment['rate'] === null ? 'Aucun Pass vendu pour ce trajet.' : $segment['rate'].' % des Pass utilisés · '.$fmt(max(0, $segment['expected'] - $segment['boarded'])).' non utilisés' }}
                        </p>
                    </x-ui.card>
                @endforeach
            </div>
            <div class="mt-6">
                <x-ui.table>
                    <x-slot:head><th>Scans refusés · motif</th><th class="text-right">Scans</th></x-slot:head>
                    @forelse ($boarding['refusals'] as $row)
                        <tr wire:key="refusal-{{ $loop->index }}"><td>{{ $row['reason'] }}</td><td class="text-right tabular-nums">{{ $fmt($row['count']) }}</td></tr>
                    @empty
                        <tr><td colspan="2" class="text-[14px] text-ink-secondary">Aucun scan refusé.</td></tr>
                    @endforelse
                    <x-slot:footer><span class="text-[13px] text-ink-secondary">{{ $fmt($boarding['scans']) }} scans au total, dont {{ $fmt($boarding['refused']) }} refusés.</span></x-slot:footer>
                </x-ui.table>
            </div>
            @break

        @case('transport')
            @include('partials.event-tabs.transport')
            @break

        @case('carte')
            @include('partials.event-tabs.carte')
            @break

        @case('incidents')
            @include('partials.event-tabs.incidents')
            @break

        @case('avis')
            <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]">
                <x-ui.card>
                    <p class="text-[14px] text-ink-secondary">Note du service</p>
                    <p class="mt-1 text-[34px] font-semibold tabular-nums">{{ \App\Services\Reporting\QualityMetrics::rating($reviews['rating']) }} <span class="text-[17px] font-normal text-ink-secondary">/ 5 · {{ $fmt($reviews['reviews']) }} avis</span></p>
                    <ul class="mt-4 space-y-3">
                        @foreach ($reviews['criteria'] as $key => $criterion)
                            <li wire:key="criterion-{{ $key }}">
                                <div class="flex justify-between text-[14px]"><span>{{ $criterion['label'] }}</span><span class="tabular-nums">{{ \App\Services\Reporting\QualityMetrics::rating($criterion['average']) }}</span></div>
                                <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-fill"><div class="h-full rounded-full bg-brand" style="width: {{ ($criterion['average'] ?? 0) / 5 * 100 }}%"></div></div>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
                <x-ui.card>
                    <h3 class="text-[17px] font-semibold tracking-tight">Commentaires des spectateurs</h3>
                    <ul class="mt-3 divide-y divide-separator">
                        @forelse ($reviews['comments'] as $comment)
                            <li wire:key="comment-{{ $loop->index }}" class="py-3">
                                <p class="text-[13px] text-ink-secondary">{{ \App\Services\Reporting\QualityMetrics::rating($comment['rating']) }} / 5 · {{ $comment['at']->translatedFormat('j M Y') }}{{ $comment['point'] ? ' · '.$comment['point'] : '' }}</p>
                                <p class="mt-0.5 text-[15px]">« {{ $comment['comment'] }} »</p>
                            </li>
                        @empty
                            <li class="py-3 text-[14px] text-ink-secondary">Aucun commentaire pour l'instant. Les spectateurs donnent leur avis après le trajet.</li>
                        @endforelse
                    </ul>
                </x-ui.card>
            </div>
            @break

        @case('reclamations')
            <div class="mb-6 grid gap-4 sm:grid-cols-3">
                <x-ui.kpi label="Ouvertes" :value="$fmt($complaints['open'])" :tone="$complaints['open'] > 0 ? 'warning' : 'default'" icon="lifebuoy" />
                <x-ui.kpi label="En cours" :value="$fmt($complaints['in_progress'])" icon="clock" />
                <x-ui.kpi label="Traitées" :value="$fmt($complaints['resolved'])" tone="success" icon="check" />
            </div>
            <x-ui.table>
                <x-slot:head><th>N°</th><th>Date</th><th>Motif</th><th>Objet</th><th>Point</th><th>Statut</th></x-slot:head>
                @forelse ($complaints['rows'] as $row)
                    <tr wire:key="complaint-{{ $row['number'] }}">
                        <td class="tabular-nums">{{ $row['number'] }}</td>
                        <td class="whitespace-nowrap">{{ $row['at']->translatedFormat('j M · H\hi') }}</td>
                        <td>{{ $row['category'] }}</td>
                        <td>{{ $row['title'] }}@if ($row['collective'])<p class="text-[12px] text-ink-secondary">Réclamation collective · {{ $fmt($row['affected']) }} passagers</p>@endif</td>
                        <td>{{ $row['point'] ?? '—' }}</td>
                        <td><x-ui.badge :tone="$row['status']->tone()">{{ $row['status']->label() }}</x-ui.badge></td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-ui.empty-state icon="lifebuoy" title="Aucune réclamation" description="La compagnie traite les réclamations des spectateurs ; vous en suivez ici l'avancement." /></td></tr>
                @endforelse
            </x-ui.table>
            @break

        @case('messages')
            <x-ui.table>
                <x-slot:head><th>Envoyé</th><th>Destinataires</th><th>Message</th><th class="text-right">Spectateurs</th><th>Statut</th></x-slot:head>
                @forelse ($messages as $message)
                    <tr wire:key="message-{{ $message->id }}">
                        <td class="whitespace-nowrap"><p>{{ $message->created_at->format('d/m H\hi') }}</p><p class="text-[12px] text-ink-secondary">{{ $message->author?->name }}</p></td>
                        <td>{{ $message->audience_label }}</td>
                        <td class="max-w-96 text-[13px]">{{ $message->body }}</td>
                        <td class="text-right tabular-nums">{{ $fmt($message->recipients_count) }}</td>
                        <td><x-ui.badge :tone="$message->status->tone()">{{ $message->status->label() }}</x-ui.badge></td>
                    </tr>
                @empty
                    <tr><td colspan="5"><x-ui.empty-state icon="chat" title="Aucun message" description="Envoyez un message aux détenteurs de Pass depuis « Messages aux spectateurs »." /></td></tr>
                @endforelse
            </x-ui.table>
            @can('promoter-messages.send')
                <x-ui.button variant="secondary" size="sm" class="mt-4" :href="route('promoter.messages', ['evenement' => $event->id])">Envoyer un message</x-ui.button>
            @endcan
            @break

        @case('annulations')
            <div class="mb-6 grid gap-4 sm:grid-cols-3">
                <x-ui.kpi label="Pass annulés" :value="$fmt($cancellations['cancelled'])" hint="En attente de remboursement" icon="ticket" />
                <x-ui.kpi label="Pass remboursés" :value="$fmt($cancellations['refunded'])" icon="lifebuoy" />
                <x-ui.kpi label="Remboursements à traiter" :value="$fmt($cancellations['pending'])" icon="clock" />
            </div>
            <x-ui.table>
                <x-slot:head><th>Motif du remboursement</th><th class="text-right">Pass</th></x-slot:head>
                @forelse ($cancellations['by_reason'] as $row)
                    <tr wire:key="reason-{{ $loop->index }}"><td>{{ $row['label'] }}</td><td class="text-right tabular-nums">{{ $fmt($row['count']) }}</td></tr>
                @empty
                    <tr><td colspan="2" class="text-[14px] text-ink-secondary">Aucune annulation.</td></tr>
                @endforelse
            </x-ui.table>
            @break

        @default
            <div class="grid gap-6 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
                <x-ui.card>
                    <h3 class="text-[17px] font-semibold tracking-tight">Informations</h3>
                    <dl class="mt-4 grid grid-cols-2 gap-4 text-[14px]">
                        <div><dt class="text-ink-secondary">Lieu</dt><dd>{{ $event->venue->name }}</dd></div>
                        <div><dt class="text-ink-secondary">Date</dt><dd>{{ ucfirst($event->starts_at->translatedFormat('l j F Y')) }}</dd></div>
                        <div><dt class="text-ink-secondary">Ouverture des portes</dt><dd>{{ $event->doors_open_at?->format('H\hi') ?? '—' }}</dd></div>
                        <div><dt class="text-ink-secondary">Début · fin</dt><dd>{{ $event->starts_at->format('H\hi') }} · {{ $event->ends_at->format('H\hi') }}</dd></div>
                        <div><dt class="text-ink-secondary">Transporteur</dt><dd>{{ $event->company->name }}</dd></div>
                        <div><dt class="text-ink-secondary">Offres</dt><dd>{{ $event->offers->map(fn ($offer) => $offer->offer->label())->join(', ') ?: '—' }}</dd></div>
                        <div><dt class="text-ink-secondary">Points d'embarquement</dt><dd>{{ $event->boardingPoints->count() }}</dd></div>
                        <div><dt class="text-ink-secondary">Zones de dépose et de reprise</dt><dd>{{ $event->dropZones->count() }}</dd></div>
                    </dl>
                    @if ($event->description)
                        <p class="mt-4 text-[14px] text-ink-secondary">{{ $event->description }}</p>
                    @endif
                    @if ($event->status === \App\Enums\EventStatus::OnSale)
                        <p class="mt-4 text-[14px]">Lien de vente : <a href="{{ route('site.events.show', $event->slug) }}" target="_blank" class="font-medium break-all text-brand-text hover:underline">{{ route('site.events.show', $event->slug) }}</a></p>
                    @endif
                    @if ($event->cancellation_reason)
                        <x-ui.alert tone="danger" class="mt-4">Motif de l'annulation : {{ $event->cancellation_reason }}</x-ui.alert>
                    @endif
                </x-ui.card>

                <x-ui.card>
                    <h3 class="text-[17px] font-semibold tracking-tight">Étapes</h3>
                    <ol class="mt-4 space-y-3">
                        @foreach ($summary['timeline'] as $step)
                            <li wire:key="step-{{ $loop->index }}" class="flex items-start gap-3">
                                <span @class(['mt-0.5 grid size-5 shrink-0 place-items-center rounded-full', 'bg-success text-white' => $step['done'], 'bg-fill-strong' => ! $step['done']])>
                                    @if ($step['done'])<x-ui.icon name="check" class="size-3" stroke-width="3" />@endif
                                </span>
                                <span class="text-[14px]">
                                    <span class="font-medium">{{ $step['label'] }}</span>
                                    <span class="block text-ink-secondary">{{ $step['at']?->translatedFormat('j M Y · H\hi') ?? 'À venir' }}</span>
                                </span>
                            </li>
                        @endforeach
                    </ol>
                </x-ui.card>
            </div>
    @endswitch
</div>
