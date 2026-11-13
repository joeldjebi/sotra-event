<div>
    <h1 class="text-[34px] leading-tight font-bold tracking-tight">Mes Pass</h1>

    <div class="mt-5 grid grid-cols-2 rounded-xl bg-fill p-0.5 text-[14px] font-medium">
        <button type="button" wire:click="$set('period', 'a-venir')" @class(['rounded-[10px] py-2 transition', 'bg-surface shadow-sm' => $period === 'a-venir', 'text-ink-secondary' => $period !== 'a-venir'])>À venir</button>
        <button type="button" wire:click="$set('period', 'passes')" @class(['rounded-[10px] py-2 transition', 'bg-surface shadow-sm' => $period === 'passes', 'text-ink-secondary' => $period !== 'passes'])>Passés</button>
    </div>

    <div class="mt-5 space-y-3">
        @forelse ($passes as $pass)
            <a wire:key="pass-{{ $pass->id }}" href="{{ route('site.passes.show', $pass) }}" class="flex items-center gap-4 rounded-[22px] bg-surface p-4 shadow-[var(--shadow-card)] ring-1 ring-hairline transition active:scale-[0.99]">
                <div class="grid size-14 shrink-0 place-items-center rounded-2xl bg-marine-900 text-white"><x-ui.icon name="qr" class="size-7" /></div>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-[17px] font-semibold">{{ $pass->event->name }}</p>
                    <p class="truncate text-[14px] text-ink-secondary">{{ $pass->event->starts_at->translatedFormat('j M') }} · {{ $pass->offer->label() }} · {{ $pass->boardingPoint->commune }} {{ $pass->boardingPoint->boardingCallAt()->format('H\hi') }}</p>
                    <p class="mt-0.5 font-mono text-[12px] text-ink-tertiary">{{ $pass->code }}</p>
                </div>
                <x-ui.badge :tone="$pass->status->tone()">{{ $pass->status->label() }}</x-ui.badge>
            </a>
        @empty
            <x-ui.card padding="p-0">
                <x-ui.empty-state icon="ticket" title="Aucun Pass" description="Réservez votre bus pour le prochain concert ou match.">
                    <x-ui.button :href="route('home')">Voir les événements</x-ui.button>
                </x-ui.empty-state>
            </x-ui.card>
        @endforelse
    </div>
</div>
