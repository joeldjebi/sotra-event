@props(['label', 'description' => null])
<label class="flex cursor-pointer items-start gap-3 rounded-xl px-1 py-1.5">
    <input type="checkbox" {{ $attributes->merge(['class' => 'mt-0.5 size-[18px] rounded-[5px] border-ink-tertiary/50 text-brand focus:ring-brand/30']) }}>
    <span>
        <span class="block text-[15px]">{{ $label }}</span>
        @if ($description)
            <span class="block text-[13px] text-ink-secondary">{{ $description }}</span>
        @endif
    </span>
</label>
