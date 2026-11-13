@extends('pdf.layout', ['title' => 'Bilan · '.$event->name, 'company' => $event->company->name])
@section('content')
    <div class="header">
        <h1>Bilan transport</h1>
        <p class="muted">{{ $event->name }} · {{ $event->venue->name }} · {{ $event->starts_at->translatedFormat('l j F Y') }} · {{ $event->promoter->name }}</p>
    </div>

    <table class="kpis">
        <tr>
            <td><div class="muted">Transportés aller</div><div class="kpi-value">{{ $report['transported']['outbound'] }}</div><div class="muted">sur {{ $report['sold'] }} Pass vendus</div></td>
            <td><div class="muted">Transportés retour</div><div class="kpi-value">{{ $report['transported']['return'] }}</div></td>
            <td><div class="muted">Départs à l'heure</div><div class="kpi-value">{{ $report['on_time_rate'] !== null ? $report['on_time_rate'].' %' : '—' }}</div><div class="muted">{{ $report['departed'] }} départs confirmés</div></td>
            <td><div class="muted">Note moyenne</div><div class="kpi-value">{{ $report['rating'] !== null ? \App\Services\Reporting\QualityMetrics::rating($report['rating']).' / 5' : '—' }}</div><div class="muted">{{ $report['reviews'] }} avis</div></td>
        </tr>
    </table>

    <h2>Incidents</h2>
    <table>
        <tr><td>Pannes</td><td class="right">{{ $report['breakdowns'] }}</td></tr>
        <tr><td>Réclamations</td><td class="right">{{ $report['complaints'] }}</td></tr>
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
