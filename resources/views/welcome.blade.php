<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    @include('partials.head', ['title' => null])
</head>
<body class="min-h-full bg-surface">
    <header class="sticky top-0 z-30 border-b border-hairline bg-white/80 backdrop-blur-xl">
        <div class="mx-auto flex h-14 max-w-6xl items-center justify-between px-4 sm:px-8">
            <x-ui.logo />
            <x-ui.button :href="route('login')" variant="secondary" size="sm">Espace professionnel</x-ui.button>
        </div>
    </header>

    <main>
        <section class="mx-auto max-w-4xl px-4 pt-20 pb-16 text-center sm:px-8 sm:pt-28">
            <p class="text-[15px] font-semibold text-brand">ACCES EVENT × SOTRA</p>
            <h1 class="mt-3 text-[44px] leading-[1.05] font-semibold tracking-tight sm:text-[64px]">Le Pass en route.</h1>
            <p class="mx-auto mt-5 max-w-2xl text-[19px] leading-relaxed text-ink-secondary sm:text-[21px]">
                Un bus aller-retour vers les grands événements, réservé depuis votre téléphone, à prix connu.
                Sans chercher à se garer.
            </p>
            <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                <x-ui.badge tone="brand" class="px-3 py-1 text-[13px]">Billetterie bientôt disponible</x-ui.badge>
            </div>
        </section>

        <section class="bg-canvas">
            <div class="mx-auto grid max-w-6xl gap-4 px-4 py-16 sm:grid-cols-3 sm:px-8">
                @foreach ([
                    ['ticket', 'Réservez', 'Choisissez votre point de départ et votre offre, payez par Mobile Money.'],
                    ['qr', 'Montez', 'Le convoyeur scanne votre Pass, même sans réseau.'],
                    ['map', 'Suivez', 'Suivez votre bus en direct, à l\'aller comme au retour.'],
                ] as [$icon, $heading, $text])
                    <x-ui.card wire:key="feature-{{ $loop->index }}" padding="p-7">
                        <span class="grid size-11 place-items-center rounded-2xl bg-brand-soft text-brand"><x-ui.icon :name="$icon" class="size-6" /></span>
                        <h2 class="mt-5 text-[21px] font-semibold tracking-tight">{{ $heading }}</h2>
                        <p class="mt-2 text-[15px] leading-relaxed text-ink-secondary">{{ $text }}</p>
                    </x-ui.card>
                @endforeach
            </div>
        </section>
    </main>

    <footer class="border-t border-hairline">
        <div class="mx-auto max-w-6xl px-4 py-8 text-[13px] text-ink-tertiary sm:px-8">© {{ date('Y') }} ACCES EVENT · EVENT TRANS</div>
    </footer>
    @livewireScripts
</body>
</html>
