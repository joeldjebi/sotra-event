@props(['label', 'description' => null])
{{-- Interrupteur façon iOS, lié par wire:model sur la case sous-jacente. --}}
<label class="flex cursor-pointer items-center justify-between gap-4 rounded-xl bg-canvas px-4 py-3">
    <span>
        <span class="block text-[15px] font-medium">{{ $label }}</span>
        @if ($description)
            <span class="block text-[13px] text-ink-secondary">{{ $description }}</span>
        @endif
    </span>
    <span class="relative inline-flex shrink-0">
        <input type="checkbox" {{ $attributes->merge(['class' => 'peer sr-only']) }}>
        <span class="h-[31px] w-[51px] rounded-full bg-fill transition peer-checked:bg-success peer-focus-visible:ring-4 peer-focus-visible:ring-brand/25"></span>
        <span class="absolute top-0.5 left-0.5 size-[27px] rounded-full bg-white shadow-[0_3px_8px_rgb(0_0_0/0.15)] transition peer-checked:translate-x-5"></span>
    </span>
</label>
