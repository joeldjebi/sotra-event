@props(['label', 'value', 'hint' => null, 'tone' => 'default'])
@php
    $tones = [
        'default' => 'bg-surface',
        'warning' => 'bg-warning-soft',
        'danger' => 'bg-danger-soft',
    ];
@endphp
<div {{ $attributes->merge(['class' => 'rounded-[var(--radius-card)] p-5 shadow-[var(--shadow-card)] ring-1 ring-hairline '.$tones[$tone]]) }}>
    <p class="text-[13px] font-medium text-ink-secondary">{{ $label }}</p>
    <p class="mt-2 text-[32px] leading-none font-semibold tracking-tight tabular-nums">{{ $value }}</p>
    @if ($hint)
        <p class="mt-2 text-[13px] text-ink-tertiary">{{ $hint }}</p>
    @endif
</div>
