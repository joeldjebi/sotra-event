{{-- Détail d'un relevé de reversement (RG-14), partagé par la console et l'espace compagnie. --}}
@php($money = fn (int $amount): string => number_format($amount, 0, ',', ' ').' FCFA')
<dl class="space-y-2.5 text-[14px]">
    <div class="flex justify-between gap-3"><dt class="text-ink-secondary">Recette brute hors frais</dt><dd class="tabular-nums">{{ $money($payout->gross) }}</dd></div>
    <div class="flex justify-between gap-3"><dt class="text-ink-secondary">Remboursements ({{ $payout->passes_refunded }} Pass)</dt><dd class="tabular-nums">− {{ $money($payout->refunds) }}</dd></div>
    <div class="flex justify-between gap-3"><dt class="text-ink-secondary">Commission ACCES EVENT</dt><dd class="tabular-nums">− {{ $money($payout->commission) }}</dd></div>
    <div class="flex justify-between gap-3 border-t border-hairline pt-2.5 text-[16px] font-semibold"><dt>Net à reverser</dt><dd class="tabular-nums">{{ $money($payout->net) }}</dd></div>
</dl>
<p class="mt-2 text-[12px] text-ink-tertiary">{{ $payout->passes_sold }} Pass vendus · échéance {{ $payout->due_on->translatedFormat('j F Y') }} (5 jours ouvrés après l'événement)</p>
