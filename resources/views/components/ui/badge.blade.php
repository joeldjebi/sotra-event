@props(['tone' => 'neutral', 'dot' => false])
@php
    // Pastille teintée, couleurs système Apple (vert, orange, rouge, gris) et accent de la marque.
    $tones = [
        'neutral' => 'bg-fill text-ink-secondary',
        'brand' => 'bg-brand-soft text-brand-text',
        'success' => 'bg-success-soft text-success',
        'warning' => 'bg-warning-soft text-warning',
        'danger' => 'bg-danger-soft text-danger',
        'info' => 'bg-info-soft text-info',
    ];
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-[3px] text-[12px] leading-4 font-semibold tracking-[-0.003em] '.($tones[$tone] ?? $tones['neutral'])]) }}>@if ($dot)<span class="size-1.5 rounded-full bg-current" aria-hidden="true"></span>@endif{{ $slot }}</span>
