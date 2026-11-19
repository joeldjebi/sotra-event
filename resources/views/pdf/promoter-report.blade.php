@extends('pdf.layout', ['title' => 'Bilan · '.$event->name, 'company' => $event->company->name])
@section('content')
    @php
        $fmt = fn ($value): string => number_format((int) $value, 0, ',', ' ');
        $rating = fn ($value): string => \App\Services\Reporting\QualityMetrics::rating($value);
    @endphp
    <div class="header">
        <h1>Bilan transport</h1>
        <p class="muted">{{ $event->name }} · {{ $event->venue->name }} · {{ $event->starts_at->translatedFormat('l j F Y') }} · {{ $event->promoter->name }} · transporteur {{ $event->company->name }}</p>
    </div>

    <table class="kpis">
        <tr>
            <td><div class="muted">Pass vendus</div><div class="kpi-value">{{ $fmt($summary['sold']) }}</div><div class="muted">sur {{ $fmt($summary['capacity']) }} places · {{ $summary['fill_rate'] ?? '—' }} %</div></td>
            <td><div class="muted">Transportés aller · retour</div><div class="kpi-value">{{ $fmt($report['transported']['outbound']) }} · {{ $fmt($report['transported']['return']) }}</div><div class="muted">{{ $fmt($summary['buses_planned']) }} bus prévus à l'aller</div></td>
            <td><div class="muted">Départs à l'heure</div><div class="kpi-value">{{ $report['on_time_rate'] !== null ? $report['on_time_rate'].' %' : '—' }}</div><div class="muted">{{ $report['departed'] }} départs confirmés</div></td>
            <td><div class="muted">Note moyenne</div><div class="kpi-value">{{ $report['rating'] !== null ? $rating($report['rating']).' / 5' : '—' }}</div><div class="muted">{{ $report['reviews'] }} avis</div></td>
        </tr>
    </table>

    <h2>Ventes</h2>
    <table>
        <tr><th>Offre</th><th class="right">Vendus</th><th class="right">Capacité</th><th class="right">Restants</th></tr>
        @foreach ($sales['by_offer'] as $row)
            <tr><td>{{ $row['offer']->label() }}</td><td class="right">{{ $fmt($row['passes']) }}</td><td class="right">{{ $fmt($row['capacity']) }}</td><td class="right">{{ $fmt($row['remaining']) }}</td></tr>
        @endforeach
    </table>
    <table style="margin-top: 6px">
        <tr>
            @foreach ($sales['by_type'] as $row)<td>{{ $row['type']->label() }} : <strong>{{ $fmt($row['passes']) }}</strong></td>@endforeach
            @foreach ($sales['by_channel'] as $row)<td>{{ $row['label'] }} : <strong>{{ $row['share'] }} %</strong></td>@endforeach
        </tr>
    </table>

    <h2>Points d'embarquement</h2>
    <table>
        <tr><th>Point</th><th>Zone</th><th>Aller · retour</th><th class="right">Pass vendus</th><th class="right">Bus</th><th class="right">Montés aller</th><th class="right">Montés retour</th></tr>
        @forelse ($points as $row)
            <tr>
                <td>{{ $row['point']->commune }} · {{ $row['point']->place }}</td>
                <td>{{ $row['point']->zone }}</td>
                <td>{{ $row['point']->departure_at->format('H\hi') }} · {{ $row['point']->return_departure_at->format('H\hi') }}</td>
                <td class="right">{{ $fmt($row['passes']) }}</td><td class="right">{{ $fmt($row['buses']) }}</td>
                <td class="right">{{ $fmt($row['boarded_outbound']) }}</td><td class="right">{{ $fmt($row['boarded_return']) }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted">Aucun point d'embarquement.</td></tr>
        @endforelse
    </table>

    <h2>Embarquement</h2>
    <table>
        <tr><th>Trajet</th><th class="right">Pass utilisés</th><th class="right">Pass attendus</th><th class="right">Taux</th></tr>
        @foreach (['outbound' => 'Aller', 'return' => 'Retour'] as $key => $label)
            <tr><td>{{ $label }}</td><td class="right">{{ $fmt($boarding[$key]['boarded']) }}</td><td class="right">{{ $fmt($boarding[$key]['expected']) }}</td><td class="right">{{ $boarding[$key]['rate'] ?? '—' }} %</td></tr>
        @endforeach
        <tr><td colspan="4" class="muted">{{ $fmt($boarding['scans']) }} scans, dont {{ $fmt($boarding['refused']) }} refusés{{ $boarding['refusals'] === [] ? '' : ' : '.collect($boarding['refusals'])->map(fn ($row) => $row['reason'].' ('.$row['count'].')')->join(', ') }}.</td></tr>
    </table>

    <h2>Départs</h2>
    <table>
        <tr><th>Sens · point</th><th>Offre</th><th>Bus</th><th class="right">Passagers</th><th>Prévu · réel</th><th>Écart</th><th>Arrivée</th></tr>
        @forelse ($transport['rows'] as $row)
            <tr>
                <td>{{ $row['direction']->label() }} · {{ $row['point'] }}</td><td>{{ $row['offer']->label() }}</td>
                <td>{{ $row['bus'] ?? '—' }}{{ $row['replaced'] ? ' (remplacé)' : '' }}</td><td class="right">{{ $fmt($row['boarded']) }}</td>
                <td>{{ $row['planned_at']->format('H\hi') }} · {{ $row['actual_at']?->format('H\hi') ?? '—' }}</td>
                <td class="{{ $row['on_time'] === null ? 'muted' : ($row['on_time'] ? 'ok' : 'late') }}">{{ $row['gap_label'] }}</td>
                <td>{{ $row['arrived_at']?->format('H\hi') ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted">Aucun départ planifié.</td></tr>
        @endforelse
    </table>

    <h2>Incidents</h2>
    <table>
        <tr><th>Heure</th><th>Bus</th><th>Panne</th><th class="right">Passagers</th><th>Issue</th></tr>
        @forelse ($incidents as $row)
            <tr><td>{{ $row['at']->format('d/m H\hi') }}</td><td>{{ $row['bus'] ?? '—' }}</td><td>{{ $row['type'] }}{{ $row['urgent'] ? ' (urgente)' : '' }}</td><td class="right">{{ $fmt($row['passengers']) }}</td><td>{{ $row['result'] ?? 'En cours' }}{{ $row['duration'] !== null ? ' · '.$row['duration'].' min' : '' }}</td></tr>
        @empty
            <tr><td colspan="5" class="muted">Aucune panne.</td></tr>
        @endforelse
    </table>

    <h2>Avis des spectateurs</h2>
    <table>
        <tr>@foreach ($reviews['criteria'] as $criterion)<td>{{ $criterion['label'] }} : <strong>{{ $rating($criterion['average']) }}</strong></td>@endforeach</tr>
    </table>
    @foreach (array_slice($reviews['comments'], 0, 10) as $comment)
        <p style="margin: 4px 0">« {{ $comment['comment'] }} » <span class="muted">— {{ $rating($comment['rating']) }} / 5{{ $comment['point'] ? ', '.$comment['point'] : '' }}</span></p>
    @endforeach

    <h2>Réclamations et annulations</h2>
    <table>
        <tr><td>Réclamations ouvertes · en cours · traitées</td><td class="right">{{ $complaints['open'] }} · {{ $complaints['in_progress'] }} · {{ $complaints['resolved'] }}</td></tr>
        @foreach ($complaints['by_category'] as $row)
            <tr><td class="muted">{{ $row['label'] }}</td><td class="right">{{ $row['count'] }}</td></tr>
        @endforeach
        <tr><td>Pass annulés · remboursés</td><td class="right">{{ $fmt($cancellations['cancelled']) }} · {{ $fmt($cancellations['refunded']) }}</td></tr>
        <tr><td>Messages envoyés aux spectateurs</td><td class="right">{{ $report['messages'] }}</td></tr>
    </table>

    <h2>Flux d'arrivée (spectateurs par tranche de 15 min)</h2>
    <table>
        <tr><th>Tranche</th><th class="right">Prévu</th><th class="right">Réel</th></tr>
        @forelse ($report['flow']['labels'] as $index => $label)
            <tr><td>{{ $label }}</td><td class="right">{{ $report['flow']['series'][0]['values'][$index] }}</td><td class="right">{{ $report['flow']['series'][1]['values'][$index] }}</td></tr>
        @empty
            <tr><td colspan="3" class="muted">Aucune donnée.</td></tr>
        @endforelse
    </table>
@endsection
