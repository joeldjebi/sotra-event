@php($rating = fn (?float $value): string => \App\Services\Reporting\QualityMetrics::rating($value))
<div>
    @include('livewire.company.quality.partials.event-bar')

    @if (! $event)
        <x-ui.card padding="p-0"><x-ui.empty-state icon="star" title="Aucun événement à analyser" description="Le bilan qualité s'affiche dès qu'un de vos événements est en vente ou terminé." /></x-ui.card>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-ui.kpi label="Note du service" :value="$summary['rating'] !== null ? $rating($summary['rating']).' / 5' : '—'"
                      :hint="$summary['reviews'].' avis · '.$summary['response_rate'].' % de réponse'" icon="star" />
            <x-ui.kpi label="Départs à l'heure" :value="$summary['on_time_rate'] !== null ? $summary['on_time_rate'].' %' : '—'"
                      :hint="$summary['on_time'].' sur '.$summary['departed'].' départs · tolérance '.\App\Services\Reporting\QualityMetrics::tolerance().' min'"
                      :tone="$summary['on_time_rate'] !== null && $summary['on_time_rate'] < 80 ? 'warning' : 'default'" icon="clock" />
            <x-ui.kpi label="Pass scannés aller / retour" :value="number_format($summary['scanned_outbound'], 0, ',', ' ').' / '.number_format($summary['scanned_return'], 0, ',', ' ')"
                      :hint="($summary['sold_outbound'] > 0 ? round($summary['scanned_outbound'] / $summary['sold_outbound'] * 100) : 0).' % des Pass vendus à l\'aller'" icon="ticket" />
            <x-ui.kpi label="Pass refusés" :value="number_format($summary['refused'], 0, ',', ' ')"
                      :hint="$summary['refused_red'].' refus · '.$summary['refused_wrong_bus'].' mauvais bus'" :tone="$summary['refused'] > 0 ? 'warning' : 'default'" icon="shield" />
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]">
            <x-ui.card>
                <h2 class="text-[17px] font-semibold tracking-tight">Notes par critère</h2>
                <p class="text-[13px] text-ink-secondary">Moyenne des avis des spectateurs, sur 5.</p>
                <div class="mt-4 space-y-4">
                    @foreach ($criteria as $key => $criterion)
                        <div wire:key="criterion-{{ $key }}">
                            <div class="flex items-baseline justify-between text-[14px]">
                                <span class="font-medium">{{ $criterion['label'] }}</span>
                                <span class="tabular-nums text-ink-secondary">{{ $rating($criterion['average']) }}</span>
                            </div>
                            <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-fill" role="progressbar" aria-label="{{ $criterion['label'] }}" aria-valuenow="{{ $criterion['average'] ?? 0 }}" aria-valuemin="0" aria-valuemax="5">
                                <div @class(['h-full rounded-full', 'bg-success' => ($criterion['average'] ?? 0) >= 4, 'bg-brand' => ($criterion['average'] ?? 0) >= 3 && ($criterion['average'] ?? 0) < 4, 'bg-warning' => ($criterion['average'] ?? 0) < 3]) style="width: {{ ($criterion['average'] ?? 0) / 5 * 100 }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-ui.card>

            <x-ui.card>
                <div class="flex items-baseline justify-between gap-3">
                    <div>
                        <h2 class="text-[17px] font-semibold tracking-tight">Réclamations</h2>
                        <p class="text-[13px] text-ink-secondary">{{ $summary['complaints_open'] }} ouverte(s) · {{ $summary['complaints_resolved'] }} traitée(s)</p>
                    </div>
                    @can(\App\Enums\Permission::ComplaintsManage->value)
                        <a href="{{ route('company.quality.complaints', ['evenement' => $event->id]) }}" class="text-[13px] font-medium text-brand-text hover:underline">Toutes les réclamations</a>
                    @endcan
                </div>
                <ul class="mt-3 divide-y divide-hairline">
                    @forelse ($complaints as $complaint)
                        <li wire:key="complaint-{{ $complaint->id }}" class="flex items-center justify-between gap-3 py-2.5 text-[14px]">
                            <span class="min-w-0">
                                <span class="font-medium">{{ $complaint->title }}</span>
                                <span class="block truncate text-[13px] text-ink-secondary">{{ $complaint->number }} · {{ $complaint->mission?->boardingPoint->commune ?? '—' }}@if ($complaint->is_collective) · {{ $complaint->affected_count }} passagers @endif</span>
                            </span>
                            <x-ui.badge :tone="$complaint->status->tone()">{{ $complaint->status->label() }}</x-ui.badge>
                        </li>
                    @empty
                        <li class="py-3 text-[14px] text-ink-tertiary">Aucune réclamation.</li>
                    @endforelse
                </ul>
            </x-ui.card>
        </div>

        <x-ui.card class="mt-6" padding="p-0">
            <div class="flex items-baseline justify-between px-5 pt-5">
                <h2 class="text-[17px] font-semibold tracking-tight">Équipages</h2>
                <a href="{{ route('company.quality.crews', ['evenement' => $event->id]) }}" class="text-[13px] font-medium text-brand-text hover:underline">Tous les équipages</a>
            </div>
            @include('livewire.company.quality.partials.crews-table', ['crews' => $crews])
        </x-ui.card>
    @endif
</div>
