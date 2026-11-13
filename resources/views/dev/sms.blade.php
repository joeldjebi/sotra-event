<x-layouts.auth title="SMS simulés">
    <h1 class="text-[22px] font-semibold tracking-tight">SMS simulés</h1>
    <p class="mt-1 text-[14px] text-ink-secondary">Boîte d'envoi locale (SMS_DRIVER=log). Visible uniquement en local.</p>

    <ul class="mt-6 divide-y divide-hairline">
        @forelse ($messages as $message)
            <li wire:key="sms-{{ $message->id }}" class="py-3">
                <div class="flex items-center justify-between text-[12px] text-ink-tertiary">
                    <span class="font-mono">{{ $message->to }}</span>
                    <span>{{ $message->created_at?->diffForHumans() }}</span>
                </div>
                <p class="mt-1 text-[14px]">{{ $message->body }}</p>
            </li>
        @empty
            <li class="py-6 text-center text-[14px] text-ink-secondary">Aucun SMS pour le moment.</li>
        @endforelse
    </ul>
</x-layouts.auth>
