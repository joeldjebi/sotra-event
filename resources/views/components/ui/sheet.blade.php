@props(['model', 'title', 'subtitle' => null, 'width' => 'max-w-lg'])
{{--
    Panneau latéral (feuille) pour les formulaires, lié à une propriété booléenne Livewire.
    Usage : <x-ui.sheet model="showForm" title="Nouveau bus"> … <x-slot:footer> … </x-slot:footer></x-ui.sheet>
--}}
<div x-data="{ open: $wire.entangle('{{ $model }}') }" x-on:keydown.escape.window="open = false">
    <div x-cloak x-show="open" x-transition.opacity.duration.200ms class="fixed inset-0 z-[60] bg-black/25 backdrop-blur-[2px]" x-on:click="open = false"></div>

    <div x-cloak x-show="open"
         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
         class="fixed inset-y-0 right-0 z-[70] flex w-full {{ $width }} flex-col bg-surface shadow-[var(--shadow-float)]"
         role="dialog" aria-modal="true">
        <header class="flex items-start justify-between gap-4 border-b border-hairline px-6 py-5">
            <div>
                <h2 class="text-[21px] font-semibold tracking-tight">{{ $title }}</h2>
                @if ($subtitle)
                    <p class="mt-0.5 text-[14px] text-ink-secondary">{{ $subtitle }}</p>
                @endif
            </div>
            <button type="button" class="grid size-8 place-items-center rounded-full bg-fill text-ink-secondary hover:text-ink" x-on:click="open = false" aria-label="Fermer">
                <x-ui.icon name="x" class="size-4" />
            </button>
        </header>

        <div class="flex-1 overflow-y-auto px-6 py-6">
            {{ $slot }}
        </div>

        @isset($footer)
            <footer class="flex items-center justify-end gap-2 border-t border-hairline bg-canvas/60 px-6 py-4">
                {{ $footer }}
            </footer>
        @endisset
    </div>
</div>
