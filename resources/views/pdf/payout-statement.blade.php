@extends('pdf.layout', ['title' => 'Relevé '.$payout->reference, 'company' => $payout->company->name])
@php($money = fn (int $amount): string => number_format($amount, 0, ',', ' ').' FCFA')
@section('content')
    <div class="header">
        <h1>Relevé de reversement {{ $payout->reference }}</h1>
        <p class="muted">{{ $payout->event->name }} · {{ $payout->event->venue->name }} · {{ $payout->event->starts_at->translatedFormat('j F Y') }} · {{ $payout->company->name }}</p>
    </div>

    <table>
        <tr><td>Pass vendus (non remboursés)</td><td class="right">{{ $payout->passes_sold }}</td></tr>
        <tr><td>Recette brute hors frais de paiement</td><td class="right">{{ $money($payout->gross) }}</td></tr>
        <tr><td>Remboursements ({{ $payout->passes_refunded }} Pass)</td><td class="right">− {{ $money($payout->refunds) }}</td></tr>
        <tr><td>Commission ACCES EVENT (taux figé sur chaque vente)</td><td class="right">− {{ $money($payout->commission) }}</td></tr>
        <tr><td><strong>Net à reverser à {{ $payout->company->name }}</strong></td><td class="right"><strong>{{ $money($payout->net) }}</strong></td></tr>
    </table>

    <h2>Échéance et statut</h2>
    <table>
        <tr><td>Échéance (5 jours ouvrés après l'événement)</td><td class="right">{{ $payout->due_on->translatedFormat('j F Y') }}</td></tr>
        <tr><td>Statut</td><td class="right">{{ $payout->status->label() }}</td></tr>
        @if ($payout->paid_at)
            <tr><td>Payé le</td><td class="right">{{ $payout->paid_at->format('d/m/Y') }} · {{ $payout->payment_method?->label() }} · {{ $payout->payment_reference }}</td></tr>
        @endif
    </table>
    <p class="muted" style="margin-top: 14px">Les frais de paiement (2 %) payés par les spectateurs et les frais d'annulation retenus restent acquis à ACCES EVENT (RG-11, RG-19).</p>
@endsection
