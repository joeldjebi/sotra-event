@props(['label', 'description' => null, 'inset' => true])
{{--
    Interrupteur iOS (51 × 31) dans une cellule « inset grouped », lié par wire:model sur la case sous-jacente.
    :inset="false" retire la cellule (pour l'empiler dans une <x-ui.list>).
--}}
<label @class([
    'group flex cursor-pointer items-center justify-between gap-4 select-none',
    'rounded-[var(--radius-control)] bg-surface px-4 py-3 shadow-[var(--shadow-card)] ring-1 ring-hairline' => $inset,
    'py-1' => ! $inset,
])>
    <span class="min-w-0">
        <span class="block text-[15px] tracking-[-0.01em]">{{ $label }}</span>
        @if ($description)
            <span class="mt-0.5 block text-footnote text-ink-secondary">{{ $description }}</span>
        @endif
    </span>
    <span class="relative inline-flex shrink-0">
        <input type="checkbox" role="switch" {{ $attributes->merge(['class' => 'peer sr-only']) }}>
        <span class="h-[31px] w-[51px] rounded-full bg-fill-strong transition-colors duration-300 ease-[var(--ease-apple)] peer-checked:bg-switch peer-focus-visible:ring-4 peer-focus-visible:ring-focus peer-disabled:opacity-50"></span>
        <span class="absolute top-0.5 left-0.5 h-[27px] w-[27px] rounded-full bg-white shadow-[0_3px_8px_rgb(0_0_0/0.15),0_3px_1px_rgb(0_0_0/0.06),0_0_0_0.5px_rgb(0_0_0/0.04)] transition-all duration-300 ease-[var(--ease-spring)] group-active:w-[33px] peer-checked:translate-x-5 peer-checked:group-active:translate-x-[14px]"></span>
    </span>
</label>
