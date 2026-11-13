@props(['tone' => 'neutral'])
@php
    $tones = [
        'neutral' => 'bg-fill text-ink-secondary',
        'brand' => 'bg-brand-soft text-brand',
        'success' => 'bg-success-soft text-success',
        'warning' => 'bg-warning-soft text-warning',
        'danger' => 'bg-danger-soft text-danger',
    ];
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-0.5 text-[12px] font-medium '.$tones[$tone]]) }}>{{ $slot }}</span>
