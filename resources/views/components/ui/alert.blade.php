@props(['tone' => 'success'])
@php
    // Bandeau teinté avec symbole, façon notification système.
    $tones = [
        'success' => ['bg-success-soft', 'text-success', 'check-circle'],
        'danger' => ['bg-danger-soft', 'text-danger', 'exclamation'],
        'warning' => ['bg-warning-soft', 'text-warning', 'exclamation'],
        'info' => ['bg-fill-subtle ring-1 ring-hairline', 'text-info', 'info'],
    ];
    [$background, $accent, $icon] = $tones[$tone] ?? $tones['success'];
@endphp
<div {{ $attributes->merge(['class' => 'flex items-start gap-3 rounded-[var(--radius-control)] px-4 py-3 text-[14px] leading-snug tracking-[-0.006em] text-ink '.$background]) }} role="status">
    <x-ui.icon :name="$icon" class="mt-px size-[18px] shrink-0 {{ $accent }}" />
    <div class="min-w-0 flex-1">{{ $slot }}</div>
</div>
