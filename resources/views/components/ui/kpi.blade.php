@props(['label', 'value', 'hint' => null, 'tone' => 'default', 'icon' => null])
@php
    // Tuile façon widget Apple : libellé discret, grand chiffre en SF Pro Rounded.
    $tones = [
        'default' => ['bg-surface', 'text-ink', 'bg-brand-soft text-brand-text'],
        'brand' => ['bg-surface', 'text-brand-text', 'bg-brand-soft text-brand-text'],
        'success' => ['bg-surface', 'text-success', 'bg-success-soft text-success'],
        'warning' => ['bg-warning-soft', 'text-warning', 'bg-warning/15 text-warning'],
        'danger' => ['bg-danger-soft', 'text-danger', 'bg-danger/15 text-danger'],
    ];
    [$background, $valueColor, $iconColor] = $tones[$tone] ?? $tones['default'];
@endphp
<div {{ $attributes->merge(['class' => 'flex min-w-0 flex-col rounded-[var(--radius-card)] p-4 shadow-[var(--shadow-card)] ring-1 ring-hairline sm:p-5 '.$background]) }}>
    <div class="flex items-start justify-between gap-3">
        <p class="text-footnote font-semibold text-ink-secondary">{{ $label }}</p>
        @if ($icon)
            <span class="grid size-7 shrink-0 place-items-center rounded-full {{ $iconColor }}"><x-ui.icon :name="$icon" class="size-4" /></span>
        @endif
    </div>
    <p class="mt-3 font-rounded text-[30px] sm:text-[34px] leading-none font-semibold tracking-[-0.02em] tabular-nums {{ $valueColor }}">{{ $value }}</p>
    @if ($hint)
        <p class="mt-2 text-footnote text-ink-tertiary">{{ $hint }}</p>
    @endif
</div>
