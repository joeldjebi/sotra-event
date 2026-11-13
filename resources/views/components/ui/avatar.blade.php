@props(['initials', 'size' => 'md'])
@php
    // Monogramme façon Contacts : dégradé discret, initiales blanches.
    $sizes = ['sm' => 'size-7 text-[11px]', 'md' => 'size-9 text-[13px]', 'lg' => 'size-12 text-[17px]'];
@endphp
<span {{ $attributes->merge(['class' => 'grid shrink-0 place-items-center rounded-full bg-gradient-to-b from-marine-700 to-marine-900 font-semibold tracking-[0.01em] text-white shadow-[inset_0_0_0_0.5px_rgb(255_255_255/0.12)] '.($sizes[$size] ?? $sizes['md'])]) }} aria-hidden="true">{{ $initials }}</span>
