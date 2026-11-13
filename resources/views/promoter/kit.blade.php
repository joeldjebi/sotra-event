<x-layouts.app title="Kit de promotion" :subtitle="auth()->user()->promoter?->name">
    <p class="-mt-4 mb-6 text-[15px] text-ink-secondary">Lien de vente du Pass, QR code et visuels à intégrer dans votre communication.</p>

    <div class="space-y-4">
        @forelse ($events as $event)
            @php($url = route('site.events.show', $event->slug))
            <x-ui.card wire:key="kit-{{ $event->id }}">
                <div class="flex flex-wrap items-start gap-6">
                    <div class="grid size-36 shrink-0 place-items-center rounded-2xl bg-white p-2 ring-1 ring-hairline [&>svg]:size-full">
                        @if ($event->status === \App\Enums\EventStatus::OnSale)
                            {!! \App\Services\Sales\QrCode::svg($url, 160) !!}
                        @else
                            <x-ui.icon name="qr" class="size-10 text-ink-tertiary" />
                        @endif
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-[19px] font-semibold tracking-tight">{{ $event->name }}</h2>
                            <x-ui.badge :tone="$event->status->tone()">{{ $event->status->label() }}</x-ui.badge>
                        </div>
                        <p class="text-[14px] text-ink-secondary">{{ $event->venue->name }} · {{ $event->starts_at->translatedFormat('l j F Y, H\hi') }}</p>

                        @if ($event->status === \App\Enums\EventStatus::OnSale)
                            <div class="mt-4 flex items-center gap-2 rounded-xl bg-canvas px-3 py-2" x-data="{ copied: false }">
                                <span class="min-w-0 flex-1 truncate font-mono text-[13px]">{{ $url }}</span>
                                <x-ui.button variant="secondary" size="sm" type="button" x-on:click="navigator.clipboard.writeText('{{ $url }}'); copied = true; setTimeout(() => copied = false, 2000)">
                                    <span x-show="!copied">Copier le lien</span><span x-cloak x-show="copied">Copié</span>
                                </x-ui.button>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <x-ui.button variant="tinted" size="sm" :href="route('promoter.kit.qr', $event)">Télécharger le QR</x-ui.button>
                                @if ($event->visualUrl())
                                    <x-ui.button variant="secondary" size="sm" :href="$event->visualUrl()" target="_blank">Visuel de l'événement</x-ui.button>
                                @endif
                            </div>
                        @else
                            <p class="mt-4 text-[14px] text-ink-tertiary">Le lien et le QR seront disponibles dès l'ouverture de la vente par ACCES EVENT.</p>
                        @endif
                    </div>
                </div>
            </x-ui.card>
        @empty
            <x-ui.card padding="p-0"><x-ui.empty-state icon="qr" title="Aucun événement à promouvoir" description="Le kit apparaît dès qu'un de vos événements est accepté par la compagnie." /></x-ui.card>
        @endforelse
    </div>
</x-layouts.app>
