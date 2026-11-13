@props(['model', 'title', 'subtitle' => null, 'width' => 'max-w-lg'])
{{--
    Feuille (sheet) pour les formulaires, liée à une propriété booléenne Livewire.
    Sur mobile : feuille iOS qui monte du bas avec poignée. Sur écran large : feuille de formulaire
    centrée façon iPadOS/macOS, fond « grouped » et barres translucides.
    Usage : <x-ui.sheet model="showForm" title="Nouveau bus"> … <x-slot:footer> … </x-slot:footer></x-ui.sheet>
--}}
<div x-data="{ open: $wire.entangle('{{ $model }}') }" x-on:keydown.escape.window="open = false">
    <div x-cloak x-show="open"
         x-transition:enter="transition-opacity ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-[60] bg-black/30 dark:bg-black/55" x-on:click="open = false" aria-hidden="true"></div>

    <div x-cloak x-show="open"
         class="pointer-events-none fixed inset-0 z-[70] flex items-end justify-center sm:items-center sm:p-6"
         x-transition:enter="transition ease-[cubic-bezier(0.32,0.72,0,1)] duration-300"
         x-transition:enter-start="translate-y-full opacity-100 sm:translate-y-3 sm:scale-[0.97] sm:opacity-0"
         x-transition:enter-end="translate-y-0 opacity-100 sm:scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="translate-y-0 opacity-100 sm:scale-100"
         x-transition:leave-end="translate-y-full opacity-100 sm:translate-y-2 sm:scale-[0.98] sm:opacity-0">
        <div class="elevated pointer-events-auto relative flex max-h-[92dvh] w-full {{ $width }} flex-col overflow-hidden rounded-t-[var(--radius-sheet)] bg-canvas text-ink shadow-[var(--shadow-float)] sm:max-h-[min(88dvh,56rem)] sm:rounded-[var(--radius-sheet)]"
             role="dialog" aria-modal="true" aria-label="{{ $title }}">
            {{-- Poignée iOS --}}
            <div class="absolute inset-x-0 top-1.5 z-10 flex justify-center sm:hidden" aria-hidden="true">
                <span class="h-[5px] w-9 rounded-full bg-ink-tertiary/40"></span>
            </div>

            <header class="ui-material relative z-[1] flex items-start justify-between gap-4 border-b border-hairline px-5 pt-6 pb-4 sm:px-6 sm:pt-5">
                <div class="min-w-0">
                    <h2 class="text-title-2">{{ $title }}</h2>
                    @if ($subtitle)
                        <p class="mt-1 text-[14px] leading-snug text-ink-secondary">{{ $subtitle }}</p>
                    @endif
                </div>
                <button type="button" class="grid size-[30px] shrink-0 place-items-center rounded-full bg-fill text-ink-secondary transition hover:bg-fill-strong hover:text-ink active:scale-90" x-on:click="open = false" aria-label="Fermer">
                    <x-ui.icon name="x" class="size-3.5" stroke-width="2.4" />
                </button>
            </header>

            <div class="flex-1 overflow-y-auto overscroll-contain px-5 py-6 sm:px-6">
                {{ $slot }}
            </div>

            @isset($footer)
                <footer class="ui-material flex flex-wrap items-center justify-end gap-2 border-t border-hairline px-5 py-3.5 pb-[max(0.875rem,env(safe-area-inset-bottom))] sm:px-6">
                    {{ $footer }}
                </footer>
            @endisset
        </div>
    </div>
</div>
