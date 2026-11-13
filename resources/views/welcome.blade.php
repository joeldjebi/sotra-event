<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    @include('partials.head', ['title' => null])
</head>
<body class="min-h-full bg-surface dark:bg-black">
    {{-- Barre de navigation globale façon apple.com : fine, translucide. --}}
    <header class="ui-material sticky top-0 z-30 border-b border-hairline">
        <div class="mx-auto flex h-12 max-w-[980px] items-center justify-between px-4 sm:px-6">
            <x-ui.logo size="sm" />
            <x-ui.button :href="route('login')" variant="secondary" size="xs">Espace professionnel</x-ui.button>
        </div>
    </header>

    <main>
        <section class="relative isolate overflow-hidden">
            <div class="pointer-events-none absolute top-[-14rem] left-1/2 -z-10 h-[38rem] w-[70rem] -translate-x-1/2 rounded-full bg-[radial-gradient(closest-side,rgb(194_65_12/0.08),transparent)] dark:bg-[radial-gradient(closest-side,rgb(226_84_26/0.18),transparent)]" aria-hidden="true"></div>
            <div class="mx-auto max-w-4xl px-4 pt-20 pb-20 text-center sm:px-8 sm:pt-32 sm:pb-28">
                <p class="text-[17px] font-semibold tracking-[-0.01em] text-brand-text sm:text-[21px]">ACCES EVENT × SOTRA</p>
                <h1 class="mt-3 text-[48px] leading-[1.04] font-bold tracking-[-0.035em] sm:text-[80px]"><span class="ui-gradient-text">Le Pass en route.</span></h1>
                <p class="mx-auto mt-6 max-w-2xl text-[19px] leading-[1.42] tracking-[-0.012em] text-ink-secondary sm:text-[24px] sm:leading-[1.33] sm:tracking-[-0.016em]">
                    Un bus aller-retour vers les grands événements, réservé depuis votre téléphone, à prix connu.
                    Sans chercher à se garer.
                </p>
                <div class="mt-9 flex flex-wrap items-center justify-center gap-3">
                    <x-ui.badge tone="brand" dot class="px-3.5 py-1.5 text-[14px]">Billetterie bientôt disponible</x-ui.badge>
                </div>
            </div>
        </section>

        <section class="bg-canvas dark:bg-[#0d0d0f]">
            <div class="mx-auto grid max-w-[1080px] gap-5 px-4 py-20 sm:grid-cols-3 sm:px-8 sm:py-24">
                @foreach ([
                    ['ticket', 'Réservez', 'Choisissez votre point de départ et votre offre, payez par Mobile Money.'],
                    ['qr', 'Montez', 'Le convoyeur scanne votre Pass, même sans réseau.'],
                    ['map', 'Suivez', 'Suivez votre bus en direct, à l\'aller comme au retour.'],
                ] as [$icon, $heading, $text])
                    <x-ui.card wire:key="feature-{{ $loop->index }}" padding="p-8" class="!rounded-[28px] transition-transform duration-300 ease-[var(--ease-apple)] hover:-translate-y-1 dark:!bg-[#1c1c1e]">
                        <span class="grid size-12 place-items-center rounded-[14px] bg-gradient-to-b from-[#e2541a] to-brand text-white shadow-[0_4px_12px_rgb(194_65_12/0.25)]"><x-ui.icon :name="$icon" class="size-6" stroke-width="1.9" /></span>
                        <h2 class="mt-6 text-[24px] leading-tight font-semibold tracking-[-0.02em]">{{ $heading }}</h2>
                        <p class="mt-2 text-[17px] leading-[1.47] tracking-[-0.012em] text-ink-secondary">{{ $text }}</p>
                    </x-ui.card>
                @endforeach
            </div>
        </section>
    </main>

    <footer class="bg-canvas dark:bg-[#0d0d0f]">
        <div class="mx-auto max-w-[1080px] border-t border-separator px-4 py-6 text-[12px] text-ink-tertiary sm:px-8">© {{ date('Y') }} ACCES EVENT · EVENT TRANS</div>
    </footer>
    @livewireScripts
</body>
</html>
