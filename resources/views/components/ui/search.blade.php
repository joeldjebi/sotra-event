@props(['placeholder' => 'Rechercher'])
<div class="relative w-full sm:w-72">
    <svg class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-tertiary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
    <input type="search" placeholder="{{ $placeholder }}"
        {{ $attributes->merge(['class' => 'h-9 w-full rounded-[10px] bg-fill pl-9 pr-3 text-[14px] text-ink placeholder:text-ink-tertiary focus:bg-surface focus:outline-none focus:ring-2 focus:ring-brand']) }}>
</div>
