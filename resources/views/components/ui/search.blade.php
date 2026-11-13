@props(['placeholder' => 'Rechercher'])
{{-- Champ de recherche macOS/iOS : remplissage gris, loupe, anneau d'accent au focus. --}}
<div class="relative w-full sm:w-72">
    <svg class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-ink-tertiary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="m20 20-4.2-4.2"/></svg>
    <input type="search" placeholder="{{ $placeholder }}" aria-label="{{ $placeholder }}"
        {{ $attributes->merge(['class' => 'h-9 w-full rounded-[10px] bg-fill pr-3 pl-8 text-base tracking-[-0.01em] text-ink transition duration-200 placeholder:text-ink-tertiary hover:bg-fill-strong focus:bg-field focus:shadow-[inset_0_0_0_1px_var(--color-brand),0_0_0_4px_var(--color-focus)] focus:outline-none sm:text-[14px]']) }}>
</div>
