{{-- Carte du Pass (SP-14 à SP-17), partagée par « Mon Pass » et le lien envoyé par SMS / WhatsApp. --}}
@php
    /** @var \App\Models\Pass $pass */
    $point = $pass->boardingPoint;
    $event = $pass->event;
    $pickup = $event->dropZones->firstWhere('usage', \App\Enums\DropZoneUsage::PickUp);
    $usable = in_array($pass->status, [\App\Enums\PassStatus::Valid, \App\Enums\PassStatus::OutboundUsed, \App\Enums\PassStatus::ReturnUsed], true);
    $outboundScanned = in_array($pass->status, [\App\Enums\PassStatus::OutboundUsed, \App\Enums\PassStatus::Completed], true);
@endphp
<div class="overflow-hidden rounded-[28px] bg-surface text-ink shadow-[var(--shadow-float)]">
    <div class="px-6 pt-6">
        <p class="text-[13px] font-semibold tracking-wide text-brand uppercase">Pass {{ $pass->offer->label() }}</p>
        <h2 class="mt-1 text-[24px] leading-tight font-semibold tracking-tight">{{ $event->name }}</h2>
        <p class="mt-1 text-[15px] text-ink-secondary">{{ ucfirst($event->starts_at->translatedFormat('l j F Y')) }}</p>
    </div>

    <div class="mt-5 flex flex-col items-center px-6">
        @if ($usable && $pass->code)
            <div class="rounded-2xl bg-white p-3 ring-1 ring-hairline [&>svg]:size-56">{!! \App\Services\Sales\QrCode::svg(app(\App\Services\Sales\PassTokenService::class)->sign($pass), 224) !!}</div>
        @else
            <div class="grid size-60 place-items-center rounded-2xl bg-fill text-center text-[15px] text-ink-secondary">Pass {{ mb_strtolower($pass->status->label()) }}</div>
        @endif
        <p class="mt-3 font-mono text-[15px] tracking-[0.2em] text-ink-secondary">{{ $pass->code }}</p>
    </div>

    <div class="mt-5 border-t border-dashed border-hairline px-6 py-5">
        <dl class="grid grid-cols-2 gap-x-4 gap-y-4">
            @if ($pass->includesOutbound())
                <div><dt class="text-[13px] text-ink-secondary">Montée</dt><dd class="text-[17px] font-semibold">{{ $point->commune }} · {{ $point->boardingCallAt()->format('H\hi') }}</dd></div>
                <div><dt class="text-[13px] text-ink-secondary">Lieu</dt><dd class="text-[17px] font-semibold">{{ $point->place }}</dd></div>
                <div><dt class="text-[13px] text-ink-secondary">Aller</dt><dd @class(['text-[17px] font-semibold', 'text-success' => ! $outboundScanned])>{{ $outboundScanned ? 'Scanné' : 'À scanner' }}</dd></div>
            @endif
            @if ($pass->includesReturn())
                <div><dt class="text-[13px] text-ink-secondary">Retour</dt><dd class="text-[17px] font-semibold">{{ $point->return_departure_at->format('H\hi') }}@if ($pickup) · {{ \Illuminate\Support\Str::after($pickup->name, '· ') }}@endif</dd></div>
            @endif
        </dl>
    </div>
</div>
<p class="mt-4 text-center text-[14px] text-ink-secondary">Présentez ce code au convoyeur avant de monter. Fonctionne aussi sans connexion.</p>
