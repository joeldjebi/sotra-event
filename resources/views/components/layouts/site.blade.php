@props(['title' => null, 'width' => 'max-w-2xl', 'back' => null])
@php
    $customer = auth('customer')->user();
    $tabs = [
        ['Événements', 'calendar', route('home'), request()->routeIs('home', 'site.events.*')],
        ['Mes Pass', 'ticket', route('site.passes.index'), request()->routeIs('site.passes.*', 'site.orders.*')],
        ['Mon trajet', 'map', route('site.trip'), request()->routeIs('site.trip')],
        ['Profil', 'users', route('site.account'), request()->routeIs('site.account', 'site.login')],
    ];
@endphp
<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    @include('partials.head', ['title' => $title])
</head>
<body class="min-h-full bg-canvas pb-24 sm:pb-0">
    <header class="sticky top-0 z-30 border-b border-hairline bg-canvas/80 backdrop-blur-xl">
        <div class="mx-auto flex h-14 max-w-5xl items-center justify-between gap-4 px-4 sm:px-6">
            <div class="flex items-center gap-2">
                @if ($back)
                    <a href="{{ $back }}" class="grid size-9 place-items-center rounded-full text-ink hover:bg-fill" aria-label="Retour">
                        <x-ui.icon name="chevron-right" class="size-5 rotate-180" />
                    </a>
                @endif
                <a href="{{ route('home') }}"><x-ui.logo /></a>
            </div>
            <nav class="hidden items-center gap-1 sm:flex" aria-label="Navigation principale">
                @foreach ($tabs as [$label, $icon, $url, $active])
                    <a href="{{ $url }}" wire:key="top-{{ $loop->index }}" @class(['rounded-full px-3.5 py-1.5 text-[14px] font-medium transition', 'bg-surface text-ink shadow-sm ring-1 ring-hairline' => $active, 'text-ink-secondary hover:text-ink' => ! $active])>{{ $label }}</a>
                @endforeach
            </nav>
        </div>
    </header>

    <main class="mx-auto {{ $width }} px-4 py-6 sm:px-6 sm:py-10">
        @if (session('flash'))
            <x-ui.alert class="mb-5">{{ session('flash') }}</x-ui.alert>
        @endif
        {{ $slot }}
    </main>

    <footer class="mx-auto hidden max-w-5xl px-6 py-10 text-[13px] text-ink-tertiary sm:block">
        © {{ date('Y') }} ACCES EVENT · EVENT TRANS · <a href="{{ route('login') }}" class="hover:text-ink">Espace professionnel</a>
    </footer>

    {{-- Barre d'onglets façon application (SP : Événements · Mes Pass · Mon trajet · Profil) --}}
    <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-hairline bg-canvas/85 pb-[env(safe-area-inset-bottom)] backdrop-blur-xl sm:hidden" aria-label="Onglets">
        <div class="grid grid-cols-4">
            @foreach ($tabs as [$label, $icon, $url, $active])
                <a href="{{ $url }}" wire:key="tab-{{ $loop->index }}" @class(['flex flex-col items-center gap-1 py-2.5 text-[11px] font-medium', 'text-brand' => $active, 'text-ink-tertiary' => ! $active])>
                    <x-ui.icon :name="$icon" class="size-6" />
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </nav>

    <x-ui.toasts />
    @livewireScripts
</body>
</html>
