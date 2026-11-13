<div>
    <h1 class="text-[28px] leading-tight font-bold tracking-tight">Paiement</h1>

    <div class="mt-6 rounded-[22px] bg-surface p-5 ring-1 ring-hairline">
        <p class="text-[17px] font-semibold">Récapitulatif</p>
        <p class="mt-0.5 text-[14px] text-ink-secondary">{{ $order->event->name }} · {{ $order->boardingPoint->commune }} · {{ $order->boardingPoint->place }}</p>
        <dl class="mt-4 space-y-2 text-[15px]">
            <div class="flex justify-between"><dt class="text-ink-secondary">Pass {{ $order->offer->label() }} {{ mb_strtolower($order->pass_type->label()) }} × {{ $order->quantity }}</dt><dd class="tabular-nums">{{ number_format($order->subtotal, 0, ',', ' ') }} FCFA</dd></div>
            <div class="flex justify-between"><dt class="text-ink-secondary">Frais de paiement ({{ rtrim(rtrim($order->fee_rate, '0'), '.') }} %)</dt><dd class="tabular-nums">{{ number_format($order->fee_amount, 0, ',', ' ') }} FCFA</dd></div>
            <div class="flex justify-between border-t border-hairline pt-2 text-[17px] font-semibold"><dt>Total</dt><dd class="tabular-nums">{{ number_format($order->total, 0, ',', ' ') }} FCFA</dd></div>
        </dl>
        @if ($order->beneficiary_phone)
            <p class="mt-3 rounded-xl bg-brand-soft px-3 py-2 text-[14px] text-brand">Cadeau pour le {{ $order->beneficiary_phone }}</p>
        @endif
    </div>

    @if ($expired)
        <x-ui.alert tone="danger" class="mt-6">Le délai de paiement est dépassé et les places ont été libérées. <a class="font-semibold underline" href="{{ route('site.events.show', $order->event->slug) }}">Recommencer</a></x-ui.alert>
    @elseif ($payment && ! $failed)
        <div wire:poll.2s="checkStatus" class="mt-6 rounded-[22px] bg-surface p-6 text-center ring-1 ring-hairline">
            <div class="mx-auto size-10 animate-spin rounded-full border-[3px] border-fill border-t-brand"></div>
            <p class="mt-4 text-[17px] font-semibold">En attente de confirmation</p>
            <p class="mt-1 text-[15px] text-ink-secondary">{{ $instructions }}</p>
            @if ($simulatorUrl)
                <a href="{{ $simulatorUrl }}" target="_blank" class="mt-4 inline-block text-[14px] font-medium text-brand underline">Démo : ouvrir le simulateur de paiement</a>
            @endif
        </div>
    @else
        @if ($failed)
            <x-ui.alert tone="danger" class="mt-6">Paiement refusé{{ $payment?->failure_reason ? ' : '.$payment->failure_reason : '' }}. Vous pouvez réessayer.</x-ui.alert>
        @endif

        <form wire:submit="pay" class="mt-6 space-y-6">
            <div>
                <p class="mb-1.5 text-[13px] font-medium text-ink-secondary">Moyen de paiement</p>
                <div class="grid grid-cols-2 gap-3">
                    @foreach ($methods as $item)
                        <button type="button" wire:key="method-{{ $item->value }}" wire:click="$set('method', '{{ $item->value }}')"
                                @class(['h-14 rounded-2xl bg-surface text-[15px] font-semibold ring-1 transition', 'ring-2 ring-brand' => $method === $item->value, 'ring-hairline hover:bg-canvas' => $method !== $item->value])>{{ $item->label() }}</button>
                    @endforeach
                </div>
            </div>

            @if (\App\Enums\PaymentMethod::tryFrom($method)?->isMobileMoney())
                <x-ui.input label="Numéro Mobile Money" name="msisdn" type="tel" wire:model="msisdn" />
                <p class="-mt-3 rounded-xl bg-fill px-4 py-3 text-[14px] text-ink-secondary">Vous allez recevoir une demande de confirmation sur votre téléphone. Votre Pass arrive aussitôt sur le site, par SMS et WhatsApp.</p>
            @else
                <p class="rounded-xl bg-fill px-4 py-3 text-[14px] text-ink-secondary">Paiement sécurisé 3-D Secure. Aucune donnée de carte n'est conservée par EVENT TRANS.</p>
            @endif

            @error('payment') <x-ui.alert tone="danger">{{ $message }}</x-ui.alert> @enderror

            <x-ui.button size="lg" class="w-full" wire:loading.attr="disabled">Payer {{ number_format($order->total, 0, ',', ' ') }} FCFA</x-ui.button>
            <p class="text-center text-[13px] text-ink-tertiary">Places réservées jusqu'à {{ $order->expires_at->format('H\hi') }}.</p>
        </form>
    @endif
</div>
