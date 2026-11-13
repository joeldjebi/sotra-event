@props(['variant' => 'primary', 'size' => 'md', 'href' => null, 'type' => 'submit', 'icon' => null])
@php
    $variants = [
        'primary' => 'bg-brand text-white hover:bg-brand-hover shadow-sm',
        'dark' => 'bg-marine-900 text-white hover:bg-marine-800 shadow-sm',
        'secondary' => 'bg-surface text-ink ring-1 ring-hairline hover:bg-canvas',
        'tinted' => 'bg-brand-soft text-brand hover:bg-brand/15',
        'ghost' => 'text-ink-secondary hover:text-ink hover:bg-fill',
        'danger' => 'bg-danger text-white hover:bg-danger/90',
    ];
    $sizes = [
        'sm' => 'h-8 px-3.5 text-[13px] gap-1.5',
        'md' => 'h-10 px-5 text-sm gap-2',
        'lg' => 'h-12 px-6 text-[15px] gap-2',
    ];
    $classes = 'inline-flex items-center justify-center rounded-full font-medium transition duration-200 ease-out active:scale-[0.98] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/25 disabled:opacity-50 disabled:pointer-events-none '
        .$variants[$variant].' '.$sizes[$size];
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-ui.icon :name="$icon" class="size-4" />@endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-ui.icon :name="$icon" class="size-4" />@endif
        {{ $slot }}
    </button>
@endif
