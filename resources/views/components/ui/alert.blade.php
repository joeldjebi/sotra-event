@props(['tone' => 'success'])
@php
    $tones = [
        'success' => 'bg-success-soft text-success',
        'danger' => 'bg-danger-soft text-danger',
        'info' => 'bg-fill text-ink',
    ];
@endphp
<div {{ $attributes->merge(['class' => 'rounded-xl px-4 py-3 text-[14px] '.$tones[$tone]]) }} role="status">{{ $slot }}</div>
