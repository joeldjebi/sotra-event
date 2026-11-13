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
<body class="min-h-full" x-data="{ menuOpen: false, scrolled: false }" x-on:scroll.window.passive="scrolled = window.scrollY > 48" x-on:keydown.escape.window="menuOpen = false">
    {{-- Voile mobile --}}
    <div x-cloak x-show="menuOpen"
         x-transition:enter="transition-opacity duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-40 bg-black/30 lg:hidden" @click="menuOpen = false" aria-hidden="true"></div>

    {{--
        Barre latérale façon macOS : panneau flottant translucide (vibrancy), en-têtes de section discrets,
        sélection en pastille de la couleur d'accent. Tiroir plein écran sur mobile.
    --}}
    <aside
        class="fixed inset-y-0 left-0 z-50 flex w-[17rem] -translate-x-full flex-col bg-sidebar backdrop-blur-2xl lg:ring-1 lg:ring-hairline backdrop-saturate-[1.8] transition-transform duration-300 ease-[var(--ease-spring)] lg:inset-y-2 lg:left-2 lg:w-64 lg:translate-x-0 lg:rounded-[18px] lg:shadow-[0_0_0_0.5px_rgb(0_0_0/0.04),0_8px_28px_rgb(0_0_0/0.06)]"
        :class="menuOpen && 'translate-x-0'"
        aria-label="Barre latérale"
    >
        <div class="flex h-14 shrink-0 items-center justify-between px-4 pt-1">
            <a href="{{ route('space') }}" class="rounded-[10px] focus-visible:outline-offset-4"><x-ui.logo /></a>
            <button type="button" class="grid size-8 place-items-center rounded-full bg-fill text-ink-secondary transition hover:text-ink active:scale-90 lg:hidden" @click="menuOpen = false" aria-label="Fermer le menu">
                <x-ui.icon name="x" class="size-3.5" stroke-width="2.4" />
            </button>
        </div>

        <div class="px-4 pt-1 pb-3">
            <p class="text-caption-2 font-semibold tracking-[0.04em] text-ink-tertiary uppercase">{{ $space?->label() }}</p>
            @if ($organisation)
                <p class="mt-0.5 truncate text-[15px] font-semibold tracking-[-0.015em]">{{ $organisation }}</p>
            @endif
        </div>

        <nav class="flex-1 space-y-5 overflow-y-auto px-2.5 pt-1 pb-6" aria-label="Menu principal">
            @foreach ($menu as $section)
                <div wire:key="section-{{ $loop->index }}">
                    @if ($section['title'])
                        <p class="mb-1 px-2.5 text-caption-2 font-semibold text-ink-tertiary">{{ $section['title'] }}</p>
                    @endif
                    <ul class="space-y-px">
                        @foreach ($section['items'] as $item)
                            <li>
                                @if ($item['url'])
                                    <a href="{{ $item['url'] }}" @class([
                                        'group flex h-8 items-center gap-2.5 rounded-[8px] px-2.5 text-[14px] tracking-[-0.01em] transition-colors duration-150',
                                        'bg-brand font-medium text-white shadow-[0_1px_2px_rgb(0_0_0/0.12)]' => $item['active'],
                                        'text-ink hover:bg-fill active:bg-fill-strong' => ! $item['active'],
                                    ]) @if ($item['active']) aria-current="page" @endif>
                                        <x-ui.icon :name="$item['icon']" :class="'size-[18px] shrink-0 '.($item['active'] ? 'text-white' : 'text-brand-text')" stroke-width="1.8" />
                                        <span class="truncate">{{ $item['label'] }}</span>
                                    </a>
                                @else
                                    <span class="flex h-8 cursor-default items-center gap-2.5 rounded-[8px] px-2.5 text-[14px] tracking-[-0.01em] text-ink-tertiary" title="Disponible prochainement">
                                        <x-ui.icon :name="$item['icon']" class="size-[18px] shrink-0 opacity-70" stroke-width="1.8" />
                                        <span class="truncate">{{ $item['label'] }}</span>
                                        <span class="ml-auto rounded-full bg-fill px-1.5 py-px text-[10px] font-semibold tracking-normal">Bientôt</span>
                                    </span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </nav>

        {{-- Profil, façon carte « Identifiant Apple » des Réglages --}}
        <div class="border-t border-hairline p-2.5" x-data="{ open: false }">
            <div class="relative">
                <button type="button" @click="open = !open" class="flex w-full items-center gap-3 rounded-[12px] p-2 text-left transition-colors hover:bg-fill active:bg-fill-strong" :aria-expanded="open" aria-haspopup="menu">
                    <x-ui.avatar :initials="$user->initials()" />
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-[14px] font-semibold tracking-[-0.01em]">{{ $user->name }}</span>
                        <span class="block truncate text-[12px] text-ink-secondary">{{ \App\Support\Navigation::roleLabel($user) }}</span>
                    </span>
                    <x-ui.icon name="chevron-up-down" class="size-4 shrink-0 text-ink-tertiary" />
                </button>
                <div x-cloak x-show="open" @click.outside="open = false"
                     x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-y-1 scale-95 opacity-0" x-transition:enter-end="translate-y-0 scale-100 opacity-100"
                     x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                     class="ui-popover elevated absolute inset-x-0 bottom-full mb-2 origin-bottom p-1.5" role="menu">
                    <a href="{{ route('security.two-factor') }}" class="flex h-9 items-center gap-2.5 rounded-[8px] px-2.5 text-[14px] transition-colors hover:bg-brand hover:text-white" role="menuitem">
                        <x-ui.icon name="shield" class="size-4" /> Sécurité du compte
                    </a>
                    <div class="mx-2.5 my-1 h-px bg-separator" role="separator"></div>
                    {{-- Apparence : Système / Clair / Sombre, appliquée immédiatement et enregistrée sur le compte. --}}
                    <div class="px-2.5 pt-1 pb-2" x-data="{
                            theme: window.eventTransTheme?.get() ?? 'system',
                            choose(value) {
                                this.theme = value;
                                window.eventTransTheme?.set(value);
                                fetch('{{ route('preferences.theme') }}', {
                                    method: 'PUT',
                                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                                    body: JSON.stringify({ theme: value }),
                                });
                            },
                        }">
                        <p class="mb-1.5 text-[12px] font-medium text-ink-secondary">Apparence</p>
                        <div class="grid grid-cols-3 gap-0.5 rounded-[9px] bg-fill p-0.5 text-[12px] font-medium" role="radiogroup" aria-label="Apparence">
                            @foreach (['system' => 'Système', 'light' => 'Clair', 'dark' => 'Sombre'] as $value => $label)
                                <button type="button" role="radio" wire:key="theme-{{ $value }}" x-on:click="choose('{{ $value }}')" :aria-checked="theme === '{{ $value }}'"
                                        :class="theme === '{{ $value }}' ? 'bg-surface text-ink shadow-[var(--shadow-control)]' : 'text-ink-secondary hover:text-ink'"
                                        class="rounded-[7px] py-1.5 transition">{{ $label }}</button>
                            @endforeach
                        </div>
                    </div>
                    <div class="mx-2.5 my-1 h-px bg-separator" role="separator"></div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="flex h-9 w-full items-center gap-2.5 rounded-[8px] px-2.5 text-[14px] text-danger transition-colors hover:bg-danger hover:text-white" role="menuitem">
                            <x-ui.icon name="logout" class="size-4" /> Se déconnecter
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </aside>

    {{-- Contenu --}}
    <main class="lg:pl-[16.5rem]">
        {{--
            Barre d'outils translucide : toujours visible sur mobile (logo, menu), et sur écran large elle
            apparaît au défilement avec le titre compact, comme le grand titre iOS qui se replie.
        --}}
        <header
            class="ui-material sticky top-0 z-30 flex h-[52px] items-center justify-between gap-3 border-b border-transparent px-4 transition-[opacity,border-color] duration-300 sm:px-8 lg:pointer-events-none lg:opacity-0"
            :class="{ 'border-hairline lg:pointer-events-auto lg:opacity-100': scrolled, 'border-transparent lg:pointer-events-none lg:opacity-0': ! scrolled }"
        >
            <a href="{{ route('space') }}" class="transition-opacity duration-200 lg:hidden" :class="{ 'opacity-0': scrolled && @js((bool) $title) }"><x-ui.logo /></a>
            @if ($title)
                <p class="pointer-events-none absolute inset-x-16 truncate text-center text-[15px] font-semibold tracking-[-0.015em] opacity-0 transition-opacity duration-200 lg:static lg:inset-auto lg:text-left"
                   :class="{ 'opacity-100': scrolled, 'opacity-0': ! scrolled }" aria-hidden="true">{{ $title }}</p>
            @endif
            <button type="button" class="grid size-9 place-items-center rounded-full text-brand-text transition hover:bg-fill active:scale-90 lg:hidden" @click="menuOpen = true" aria-label="Ouvrir le menu">
                <x-ui.icon name="menu" stroke-width="2" />
            </button>
        </header>

        <div class="mx-auto -mt-[52px] max-w-6xl px-4 pt-[4.5rem] pb-16 sm:px-8 lg:pt-[3.75rem]">
            @if ($title)
                <header class="mb-7 flex flex-wrap items-end justify-between gap-x-4 gap-y-3">
                    <div class="min-w-0">
                        @if ($subtitle)
                            <p class="text-[13px] font-semibold tracking-[-0.004em] text-ink-secondary first-letter:uppercase">{{ $subtitle }}</p>
                        @endif
                        <h1 class="mt-0.5 text-[30px] leading-tight font-bold tracking-[-0.026em] sm:text-large-title">{{ $title }}</h1>
                    </div>
                    @isset($actions)
                        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
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
