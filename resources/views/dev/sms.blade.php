<x-layouts.auth title="SMS simulés">
    <h1 class="text-title-2">SMS simulés</h1>
    <p class="mt-1 text-[14px] text-ink-secondary">Boîte d'envoi locale (SMS_DRIVER=log). Visible uniquement en local.</p>

    <ul class="mt-6 space-y-4">
        @forelse ($messages as $message)
            <li wire:key="sms-{{ $message->id }}">
                <div class="flex items-center justify-between px-3 text-caption-2 font-medium text-ink-tertiary">
                    <span class="font-mono">{{ $message->to }}</span>
                    <span>{{ $message->created_at?->diffForHumans() }}</span>
                </div>
                <p class="mt-1 w-fit max-w-[90%] rounded-[18px] rounded-bl-[6px] bg-bubble px-3.5 py-2 text-[15px] leading-snug">{{ $message->body }}</p>
            </li>
        @empty
            <li class="py-6 text-center text-[14px] text-ink-secondary">Aucun SMS pour le moment.</li>
        @endforelse
    </ul>

    @isset($outbound)
        <h2 class="mt-8 text-[17px] font-semibold tracking-tight">WhatsApp et notifications push</h2>
        <ul class="mt-3 divide-y divide-hairline">
            @forelse ($outbound as $item)
                <li wire:key="out-{{ $item->id }}" class="py-3">
                    <div class="flex items-center justify-between text-[12px] text-ink-tertiary">
                        <span><x-ui.badge>{{ $item->channel }}</x-ui.badge> <span class="font-mono">{{ $item->recipient }}</span></span>
                        <span>{{ $item->created_at?->diffForHumans() }}</span>
                    </div>
                    <p class="mt-1 text-[14px]">{{ $item->body }}</p>
                </li>
            @empty
                <li class="py-6 text-center text-[14px] text-ink-secondary">Aucun message.</li>
            @endforelse
        </ul>
    @endisset
</x-layouts.auth>
