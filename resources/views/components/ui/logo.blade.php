@props(['size' => 'md', 'inverted' => false, 'stacked' => false])
@php
    $sizes = [
        'sm' => ['size-7 rounded-[8px]', 'text-[14px]'],
        'md' => ['size-8 rounded-[9px]', 'text-[15px]'],
        'lg' => ['size-14 rounded-[16px]', 'text-[22px]'],
        'xl' => ['size-20 rounded-[22px]', 'text-[28px]'],
    ];
    [$box, $text] = $sizes[$size] ?? $sizes['md'];
@endphp
{{-- Icône d'app (squircle marine avec reflet) et logotype. --}}
<span {{ $attributes->merge(['class' => 'inline-flex items-center '.($stacked ? 'flex-col gap-3' : 'gap-2.5')]) }}>
    <span class="{{ $box }} relative grid shrink-0 place-items-center overflow-hidden bg-gradient-to-b from-marine-700 to-marine-950 shadow-[0_1px_2px_rgb(0_0_0/0.18),0_4px_12px_rgb(15_27_45/0.18)] ring-1 ring-black/5 dark:ring-white/10">
        <span class="absolute inset-x-0 top-0 h-1/2 bg-gradient-to-b from-white/12 to-transparent" aria-hidden="true"></span>
        <svg viewBox="0 0 24 24" class="relative w-3/5" fill="none" aria-hidden="true">
            <rect x="4" y="5" width="16" height="12" rx="3" stroke="#fff" stroke-width="1.8"/>
            <path d="M4 11h16" stroke="#fff" stroke-width="1.8"/>
            <circle cx="8" cy="19" r="1.6" fill="#f97316"/>
            <circle cx="16" cy="19" r="1.6" fill="#f97316"/>
        </svg>
    </span>
    <span class="{{ $text }} font-semibold tracking-[-0.02em] {{ $inverted ? 'text-white' : 'text-ink' }}">EVENT TRANS</span>
</span>
