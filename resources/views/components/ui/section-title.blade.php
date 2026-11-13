@props(['title', 'description' => null])
<div {{ $attributes->merge(['class' => 'mb-3']) }}>
    <h3 class="text-[13px] font-semibold uppercase tracking-wide text-ink-tertiary">{{ $title }}</h3>
    @if ($description)
        <p class="mt-0.5 text-[13px] text-ink-secondary">{{ $description }}</p>
    @endif
</div>
