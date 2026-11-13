@props(['label', 'description' => null])
{{-- Case à cocher macOS (carré arrondi rempli de l'accent). --}}
<label class="flex cursor-pointer items-start gap-3 rounded-[10px] px-1 py-2 transition-colors hover:bg-fill-subtle">
    <input type="checkbox" {{ $attributes->merge(['class' => 'ui-check mt-px']) }}>
    <span class="min-w-0">
        <span class="block text-[15px] leading-5 tracking-[-0.01em]">{{ $label }}</span>
        @if ($description)
            <span class="mt-0.5 block text-footnote text-ink-secondary">{{ $description }}</span>
        @endif
    </span>
</label>
