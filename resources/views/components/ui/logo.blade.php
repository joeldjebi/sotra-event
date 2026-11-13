@props(['size' => 'md', 'inverted' => false])
@php
    $box = $size === 'lg' ? 'size-12 rounded-[14px]' : 'size-8 rounded-[10px]';
    $text = $size === 'lg' ? 'text-xl' : 'text-[15px]';
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5']) }}>
    <span class="{{ $box }} grid place-items-center bg-marine-900 shadow-sm ring-1 ring-white/10">
        <svg viewBox="0 0 24 24" class="w-3/5" fill="none" aria-hidden="true">
            <rect x="4" y="5" width="16" height="12" rx="3" stroke="#fff" stroke-width="1.8"/>
            <path d="M4 11h16" stroke="#fff" stroke-width="1.8"/>
            <circle cx="8" cy="19" r="1.6" fill="#f97316"/>
            <circle cx="16" cy="19" r="1.6" fill="#f97316"/>
        </svg>
    </span>
    <span class="{{ $text }} font-semibold tracking-tight {{ $inverted ? 'text-white' : 'text-ink' }}">EVENT TRANS</span>
</span>
