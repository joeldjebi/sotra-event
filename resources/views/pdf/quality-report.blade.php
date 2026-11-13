@extends('pdf.layout', ['title' => 'Rapport qualité · '.$event->name, 'company' => $event->company->name])
@php($rating = fn (?float $value): string => \App\Services\Reporting\QualityMetrics::rating($value))
@section('content')
    <div class="header">
        <h1>Rapport qualité de service</h1>
        <p class="muted">{{ $event->name }} · {{ $event->venue->name }} · {{ $event->starts_at->translatedFormat('l j F Y') }} · {{ $event->company->name }}</p>
    </div>

    <table class="kpis">
        <tr>
            <td><div class="muted">Note du service</div><div class="kpi-value">{{ $summary['rating'] !== null ? $rating($summary['rating']).' / 5' : '—' }}</div><div class="muted">{{ $summary['reviews'] }} avis · {{ $summary['response_rate'] }} % de réponse</div></td>
            <td><div class="muted">Départs à l'heure</div><div class="kpi-value">{{ $summary['on_time_rate'] !== null ? $summary['on_time_rate'].' %' : '—' }}</div><div class="muted">{{ $summary['on_time'] }} sur {{ $summary['departed'] }} (≤ +{{ \App\Services\Reporting\QualityMetrics::tolerance() }} min)</div></td>
            <td><div class="muted">Pass scannés aller / retour</div><div class="kpi-value">{{ $summary['scanned_outbound'] }} / {{ $summary['scanned_return'] }}</div><div class="muted">sur {{ $summary['sold_outbound'] }} / {{ $summary['sold_return'] }} vendus</div></td>
            <td><div class="muted">Pass refusés</div><div class="kpi-value">{{ $summary['refused'] }}</div><div class="muted">{{ $summary['refused_red'] }} refus · {{ $summary['refused_wrong_bus'] }} mauvais bus</div></td>
        </tr>
    </table>

    <h2>Notes par critère</h2>
    <table>
        @foreach ($criteria as $criterion)
            <tr><td>{{ $criterion['label'] }}</td><td class="right">{{ $rating($criterion['average']) }} / 5</td></tr>
        @endforeach
    </table>

    <h2>Équipages (départs aller)</h2>
    <table>
        <tr><th>Bus</th><th>Point</th><th>Chauffeur</th><th>Convoyeur</th><th>Départ réel</th><th>Écart</th><th class="right">Avis</th></tr>
        @forelse ($crews as $crew)
            <tr>
                <td>Bus {{ $crew['mission']->bus?->number }}</td>
                <td>{{ $crew['mission']->boardingPoint->commune }}</td>
                <td>{{ $crew['mission']->driver?->fullName() ?? '—' }} ({{ $rating($crew['driver_rating']) }})</td>
                <td>{{ $crew['mission']->conveyor?->fullName() ?? '—' }} ({{ $rating($crew['conveyor_rating']) }})</td>
                <td>{{ $crew['mission']->actual_departure_at?->format('H\hi') ?? '—' }}</td>
                <td class="{{ $crew['on_time'] === null ? 'muted' : ($crew['on_time'] ? 'ok' : 'late') }}">{{ $crew['gap_label'] }}</td>
                <td class="right">{{ $crew['reviews'] }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted">Aucun équipage affecté.</td></tr>
        @endforelse
    </table>

    @foreach (['aller' => 'Ponctualité · aller', 'retour' => 'Ponctualité · retour'] as $direction => $heading)
        <h2>{{ $heading }}</h2>
        <table>
            <tr><th>Point</th><th>Offre</th><th>Bus</th><th>Prévu</th><th>Réel</th><th>Écart</th></tr>
            @forelse ($punctuality[$direction] as $row)
                <tr>
                    <td>{{ $row['mission']->boardingPoint->commune }}</td>
                    <td>{{ $row['mission']->offer->label() }}</td>
                    <td>{{ $row['mission']->bus ? 'Bus '.$row['mission']->bus->number : '—' }}</td>
                    <td>{{ $row['mission']->planned_departure_at->format('H\hi') }}</td>
                    <td>{{ $row['mission']->actual_departure_at?->format('H\hi') ?? '—' }}</td>
                    <td class="{{ $row['on_time'] === null ? 'muted' : ($row['on_time'] ? 'ok' : 'late') }}">{{ $row['gap_label'] }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">Aucun départ.</td></tr>
            @endforelse
        </table>
    @endforeach

    <h2>Pannes ({{ $breakdowns->count() }})</h2>
    <table>
        @forelse ($breakdowns as $alert)
            <tr>
                <td>{{ $alert->created_at->format('H\hi') }}</td>
                <td>Bus {{ $alert->bus?->number ?? '?' }} · {{ $alert->mission->boardingPoint->commune }}</td>
                <td>{{ $alert->type->label() }}</td>
                <td>{{ $alert->intervention?->result?->label() ?? $alert->status->label() }}</td>
            </tr>
        @empty
            <tr><td class="muted">Aucune panne.</td></tr>
        @endforelse
    </table>

    <h2>Réclamations ({{ $summary['complaints_open'] }} ouverte(s) · {{ $summary['complaints_resolved'] }} traitée(s))</h2>
    <table>
        @forelse ($complaints as $complaint)
            <tr>
                <td>{{ $complaint->number }}</td>
                <td>{{ $complaint->title }}@if ($complaint->is_collective) ({{ $complaint->affected_count }} passagers)@endif</td>
                <td>{{ $complaint->mission?->boardingPoint->commune ?? '—' }}</td>
                <td>{{ $complaint->status->label() }}</td>
            </tr>
        @empty
            <tr><td class="muted">Aucune réclamation.</td></tr>
        @endforelse
    </table>
@endsection
