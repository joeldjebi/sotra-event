@props(['title' => null])
{{-- Pages d'authentification façon « Identifiant Apple » / iCloud : icône d'app, carte centrée, champs à libellé flottant. --}}
<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    @include('partials.head', ['title' => $title])
</head>
<body class="min-h-full">
    <div class="relative isolate flex min-h-dvh flex-col items-center justify-center overflow-hidden px-4 py-12 sm:py-16">
        {{-- Halo discret aux couleurs de la charte --}}
        <div class="pointer-events-none absolute -top-48 left-1/2 -z-10 h-[34rem] w-[64rem] -translate-x-1/2 rounded-full bg-[radial-gradient(closest-side,rgb(194_65_12/0.10),transparent)] dark:bg-[radial-gradient(closest-side,rgb(226_84_26/0.16),transparent)]" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -bottom-64 left-1/2 -z-10 h-[30rem] w-[56rem] -translate-x-1/2 rounded-full bg-[radial-gradient(closest-side,rgb(15_27_45/0.06),transparent)] dark:bg-[radial-gradient(closest-side,rgb(64_96_160/0.14),transparent)]" aria-hidden="true"></div>

        <a href="{{ route('home') }}" class="mb-8 rounded-[16px]"><x-ui.logo size="lg" stacked /></a>

        <div class="w-full max-w-[420px]">
            <div class="rounded-[var(--radius-sheet)] bg-surface/85 p-7 shadow-[0_0_0_0.5px_rgb(0_0_0/0.05),0_12px_40px_rgb(0_0_0/0.06),0_2px_6px_rgb(0_0_0/0.03)] ring-1 ring-hairline backdrop-blur-2xl sm:p-10 dark:shadow-none">
                {{ $slot }}
            </div>
            <p class="mt-8 text-center text-footnote text-ink-tertiary">Le Pass en route · ACCES EVENT</p>
        </div>
    </div>
    @livewireScripts
</body>
</html>
