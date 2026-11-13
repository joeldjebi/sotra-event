<div>
    <p class="text-[17px] text-ink-secondary">{{ $firstName ? 'Bonjour '.$firstName : 'Bonjour' }}</p>
    <h1 class="text-[34px] leading-tight font-bold tracking-tight">Où allez-vous ?</h1>

    <div class="mt-5">
        <div class="relative">
            <svg class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-ink-tertiary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Rechercher un concert, un match…"
                   class="h-13 w-full rounded-2xl bg-surface py-3.5 pr-4 pl-12 text-[17px] shadow-[var(--shadow-card)] ring-1 ring-hairline placeholder:text-ink-tertiary focus:ring-2 focus:ring-brand focus:outline-none">
        </div>
    </div>

    @if ($featured)
        @php($rest = $events->reject(fn ($event) => $event->id === $featured->id))
        <a href="{{ route('site.events.show', $featured->slug) }}" class="mt-6 block overflow-hidden rounded-[28px] bg-marine-900 p-6 text-white shadow-[var(--shadow-float)] transition active:scale-[0.99]">
            <p class="text-[13px] font-semibold tracking-wide text-orange-300 uppercase">{{ $featured->starts_at->translatedFormat('l j M') }} · {{ $featured->starts_at->format('H\h') }}</p>
            <h2 class="mt-2 text-[28px] leading-tight font-semibold tracking-tight">{{ $featured->name }}</h2>
            <p class="mt-1.5 text-[16px] text-white/70">Bus aller-retour depuis {{ $featured->communes_count }} {{ \Illuminate\Support\Str::plural('commune', $featured->communes_count) }}</p>
            <div class="mt-6 flex items-center justify-between">
                <p class="text-[16px]">Pass dès <span class="font-semibold">{{ number_format((int) $featured->from_price, 0, ',', ' ') }} FCFA</span></p>
                <span class="inline-flex h-11 items-center rounded-full bg-brand px-6 text-[15px] font-semibold">Réserver</span>
            </div>
        </a>
    @else
        @php($rest = $events)
    @endif

    @if ($rest->isNotEmpty() || ! $featured)
    <h2 class="mt-9 mb-3 text-[22px] font-semibold tracking-tight">{{ $search === '' ? 'Bientôt' : 'Résultats' }}</h2>
    <div class="space-y-3">
        @forelse ($rest as $event)
            <a wire:key="event-{{ $event->id }}" href="{{ route('site.events.show', $event->slug) }}" class="flex items-center gap-4 rounded-[22px] bg-surface p-4 shadow-[var(--shadow-card)] ring-1 ring-hairline transition active:scale-[0.99]">
                <div class="grid w-16 shrink-0 place-items-center rounded-2xl bg-brand-soft py-2.5 text-brand">
                    <span class="text-[22px] leading-none font-bold">{{ $event->starts_at->format('d') }}</span>
                    <span class="text-[12px] font-semibold uppercase">{{ $event->starts_at->translatedFormat('M') }}</span>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-[17px] font-semibold">{{ $event->name }}</p>
                    <p class="truncate text-[15px] text-ink-secondary">{{ $event->venue->name }} · dès {{ number_format((int) $event->from_price, 0, ',', ' ') }} FCFA</p>
                </div>
                <x-ui.icon name="chevron-right" class="size-5 text-ink-tertiary" />
            </a>
        @empty
            <x-ui.card padding="p-0">
                <x-ui.empty-state icon="calendar" :title="$search === '' ? 'Aucun événement en vente pour le moment' : 'Aucun résultat'" description="Revenez bientôt : les prochains concerts et matchs arrivent." />
            </x-ui.card>
        @endforelse
    </div>
    @endif
</div>
