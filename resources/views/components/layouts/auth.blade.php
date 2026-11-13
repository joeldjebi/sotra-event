@props(['title' => null])
<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    @include('partials.head', ['title' => $title])
</head>
<body class="min-h-full">
    <div class="relative flex min-h-screen flex-col items-center justify-center px-4 py-12">
        {{-- Halo discret aux couleurs de la charte --}}
        <div class="pointer-events-none absolute inset-x-0 top-0 h-80 bg-gradient-to-b from-brand/[0.06] to-transparent"></div>

        <a href="{{ route('home') }}" class="relative mb-8"><x-ui.logo size="lg" /></a>

        <div class="relative w-full max-w-[400px]">
            <div class="rounded-[28px] bg-surface/90 p-8 shadow-[var(--shadow-float)] ring-1 ring-hairline backdrop-blur-xl sm:p-10">
                {{ $slot }}
            </div>
            <p class="mt-6 text-center text-[13px] text-ink-tertiary">Le Pass en route · ACCES EVENT</p>
        </div>
    </div>
    @livewireScripts
</body>
</html>
