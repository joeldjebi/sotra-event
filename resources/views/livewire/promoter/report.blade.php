<div>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div class="w-full sm:w-80">
            <x-ui.select label="Événement" name="eventId" wire:model.live="eventId" :options="$eventOptions" placeholder="Choisir un événement" />
        </div>
        @if ($event)
            <x-ui.button variant="secondary" icon="document" :href="route('promoter.report.pdf', ['event' => $event->id])">Exporter le bilan (PDF)</x-ui.button>
        @endif
    </div>

    @if (! $event)
        <x-ui.card padding="p-0"><x-ui.empty-state icon="document" title="Aucun bilan" description="Le bilan est disponible dès la vente de votre événement, et complet le lendemain." /></x-ui.card>
    @else
        <div class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
            <x-ui.kpi label="Transportés aller" :value="number_format($report['transported']['outbound'], 0, ',', ' ')" :hint="'sur '.number_format($report['sold'], 0, ',', ' ').' Pass vendus'" icon="users" />
            <x-ui.kpi label="Transportés retour" :value="number_format($report['transported']['return'], 0, ',', ' ')" icon="route" />
            <x-ui.kpi label="Départs à l'heure" :value="$report['on_time_rate'] !== null ? $report['on_time_rate'].' %' : '—'" :hint="$report['departed'].' départs confirmés'" icon="clock" />
            <x-ui.kpi label="Note moyenne" :value="$report['rating'] !== null ? \App\Services\Reporting\QualityMetrics::rating($report['rating']).' / 5' : '—'" :hint="$report['reviews'].' avis'" icon="star" />
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
            <x-ui.card>
                <h2 class="text-[17px] font-semibold tracking-tight">Flux d'arrivée</h2>
                @if ($report['flow']['labels'] === [])
                    <x-ui.empty-state icon="chart" title="Pas de données" description="Le flux apparaît dès que le plan de transport est généré." />
                @else
                    <x-ui.chart class="mt-4" :config="$report['flow']" label="Flux d'arrivée prévu et réel" />
                @endif
            </x-ui.card>
            <x-ui.card>
                <h2 class="text-[17px] font-semibold tracking-tight">Incidents</h2>
                <dl class="mt-4 space-y-3 text-[14px]">
                    <div class="flex justify-between"><dt class="text-ink-secondary">Pannes</dt><dd class="font-medium tabular-nums">{{ $report['breakdowns'] }}</dd></div>
                    <div class="flex justify-between"><dt class="text-ink-secondary">Réclamations</dt><dd class="font-medium tabular-nums">{{ $report['complaints'] }}</dd></div>
                    <div class="flex justify-between"><dt class="text-ink-secondary">Messages envoyés aux spectateurs</dt><dd class="font-medium tabular-nums">{{ $report['messages'] }}</dd></div>
                </dl>
            </x-ui.card>
        </div>
    @endif
</div>
