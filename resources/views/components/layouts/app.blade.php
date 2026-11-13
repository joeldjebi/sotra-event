@props(['title' => null, 'subtitle' => null])
@php
    /** @var \App\Models\User $user */
    $user = auth()->user();
    $menu = \App\Support\Navigation::for($user);
    $space = $user->space();
    $organisation = $user->company?->name ?? $user->promoter?->name;
@endphp
<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    @include('partials.head', ['title' => $title])
</head>
<body class="min-h-full" x-data="{ menuOpen: false }">
    {{-- Barre supérieure mobile --}}
    <header class="sticky top-0 z-30 flex h-14 items-center justify-between border-b border-hairline bg-canvas/80 px-4 backdrop-blur-xl lg:hidden">
        <x-ui.logo />
        <button type="button" class="grid size-10 place-items-center rounded-full text-ink hover:bg-fill" @click="menuOpen = true" aria-label="Ouvrir le menu">
            <x-ui.icon name="menu" />
        </button>
    </header>

    {{-- Voile mobile --}}
    <div x-cloak x-show="menuOpen" x-transition.opacity class="fixed inset-0 z-40 bg-black/20 backdrop-blur-sm lg:hidden" @click="menuOpen = false"></div>

    {{-- Barre latérale translucide --}}
    <aside
        class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col border-r border-hairline bg-white/75 backdrop-blur-2xl transition-transform duration-300 ease-out lg:translate-x-0"
        :class="menuOpen && 'translate-x-0'"
    >
        <div class="flex h-16 items-center justify-between px-5">
            <a href="{{ route('space') }}"><x-ui.logo /></a>
            <button type="button" class="grid size-9 place-items-center rounded-full text-ink-secondary hover:bg-fill lg:hidden" @click="menuOpen = false" aria-label="Fermer le menu">
                <x-ui.icon name="x" class="size-4" />
            </button>
        </div>

        <div class="px-5 pb-4">
            <p class="text-[12px] font-medium uppercase tracking-wide text-ink-tertiary">{{ $space?->label() }}</p>
            @if ($organisation)
                <p class="mt-0.5 truncate text-[15px] font-semibold tracking-tight">{{ $organisation }}</p>
            @endif
        </div>

        <nav class="flex-1 space-y-6 overflow-y-auto px-3 pb-6" aria-label="Menu principal">
            @foreach ($menu as $section)
                <div wire:key="section-{{ $loop->index }}">
                    @if ($section['title'])
                        <p class="mb-1.5 px-3 text-[12px] font-medium text-ink-tertiary">{{ $section['title'] }}</p>
                    @endif
                    <ul class="space-y-0.5">
                        @foreach ($section['items'] as $item)
                            <li>
                                @if ($item['url'])
                                    <a href="{{ $item['url'] }}" @class([
                                        'group flex h-9 items-center gap-3 rounded-[10px] px-3 text-[14px] transition',
                                        'bg-brand-soft font-medium text-brand' => $item['active'],
                                        'text-ink hover:bg-fill' => ! $item['active'],
                                    ]) @if ($item['active']) aria-current="page" @endif>
                                        <x-ui.icon :name="$item['icon']" class="size-[18px]" />
                                        <span class="truncate">{{ $item['label'] }}</span>
                                    </a>
                                @else
                                    <span class="flex h-9 cursor-default items-center gap-3 rounded-[10px] px-3 text-[14px] text-ink-tertiary" title="Disponible prochainement">
                                        <x-ui.icon :name="$item['icon']" class="size-[18px]" />
                                        <span class="truncate">{{ $item['label'] }}</span>
                                        <span class="ml-auto rounded-full bg-fill px-1.5 py-px text-[10px] font-medium">Bientôt</span>
                                    </span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </nav>

        {{-- Profil --}}
        <div class="border-t border-hairline p-3" x-data="{ open: false }">
            <div class="relative">
                <button type="button" @click="open = !open" class="flex w-full items-center gap-3 rounded-xl p-2 text-left hover:bg-fill">
                    <span class="grid size-9 shrink-0 place-items-center rounded-full bg-marine-900 text-[13px] font-semibold text-white">{{ $user->initials() }}</span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-[14px] font-medium">{{ $user->name }}</span>
                        <span class="block truncate text-[12px] text-ink-secondary">{{ \App\Support\Navigation::roleLabel($user) }}</span>
                    </span>
                    <x-ui.icon name="chevron-down" class="size-4 text-ink-tertiary" />
                </button>
                <div x-cloak x-show="open" @click.outside="open = false" x-transition.origin.bottom
                     class="absolute inset-x-0 bottom-full mb-2 overflow-hidden rounded-2xl bg-surface p-1.5 shadow-[var(--shadow-float)] ring-1 ring-hairline">
                    <a href="{{ route('security.two-factor') }}" class="flex items-center gap-2.5 rounded-xl px-3 py-2 text-[14px] hover:bg-fill">
                        <x-ui.icon name="shield" class="size-4 text-ink-secondary" /> Sécurité du compte
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2 text-[14px] text-danger hover:bg-danger-soft">
                            <x-ui.icon name="logout" class="size-4" /> Se déconnecter
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </aside>

    {{-- Contenu --}}
    <main class="lg:pl-72">
        <div class="mx-auto max-w-6xl px-4 py-8 sm:px-8 lg:py-12">
            @if ($title)
                <header class="mb-8 flex flex-wrap items-end justify-between gap-4">
                    <div>
                        @if ($subtitle)
                            <p class="text-[13px] font-medium text-ink-secondary">{{ $subtitle }}</p>
                        @endif
                        <h1 class="mt-1 text-[34px] leading-tight font-semibold tracking-tight">{{ $title }}</h1>
                    </div>
                    @isset($actions)
                        <div class="flex items-center gap-2">{{ $actions }}</div>
                    @endisset
                </header>
            @endif

            @if (session('flash'))
                <x-ui.alert class="mb-6">{{ session('flash') }}</x-ui.alert>
            @endif

            {{ $slot }}
        </div>
    </main>
    <x-ui.toasts />
    @livewireScripts
</body>
</html>
