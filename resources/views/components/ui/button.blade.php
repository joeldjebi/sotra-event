@props(['variant' => 'primary', 'size' => 'md', 'href' => null, 'type' => 'submit', 'icon' => null, 'iconTrailing' => null])
@php
    /*
    | Boutons Apple : filled (primary, dark, danger), tinted, gray (secondary), plain, ghost.
    | « secondary » reste l'alias historique du bouton gris neutre (Annuler, Modifier).
    */
    $variants = [
        'primary' => 'bg-brand text-white shadow-[inset_0_1px_0_rgb(255_255_255/0.12),0_1px_2px_rgb(0_0_0/0.08)] hover:bg-brand-hover',
        'dark' => 'bg-inverse text-on-inverse shadow-[0_1px_2px_rgb(0_0_0/0.08)] hover:bg-inverse-hover',
        'secondary' => 'bg-fill text-ink hover:bg-fill-strong',
        'gray' => 'bg-fill text-brand-text hover:bg-fill-strong',
        'tinted' => 'bg-brand-soft text-brand-text hover:bg-brand/20',
        'bordered' => 'bg-surface text-ink shadow-[var(--shadow-control)] ring-1 ring-line hover:bg-fill-subtle',
        'plain' => 'text-brand-text hover:opacity-70',
        'ghost' => 'text-ink-secondary hover:bg-fill hover:text-ink',
        'danger' => 'bg-danger text-white shadow-[0_1px_2px_rgb(0_0_0/0.08)] hover:brightness-110',
        'danger-tinted' => 'bg-danger-soft text-danger hover:bg-danger/20',
    ];
    $sizes = [
        'xs' => 'h-7 px-3 text-[12px] gap-1',
        'sm' => 'h-8 px-3.5 text-[13px] gap-1.5',
        'md' => 'h-10 px-[1.125rem] text-[15px] gap-2',
        'lg' => 'h-12 px-6 text-[17px] gap-2',
    ];
    $iconSizes = ['xs' => 'size-3.5', 'sm' => 'size-4', 'md' => 'size-[18px]', 'lg' => 'size-5'];
    $classes = 'inline-flex select-none items-center justify-center whitespace-nowrap rounded-full font-medium tracking-[-0.01em] '
        .'transition-[background-color,color,box-shadow,opacity,transform,filter] duration-200 ease-[var(--ease-apple)] active:scale-[0.97] '
        .'focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-focus '
        .'disabled:pointer-events-none disabled:opacity-40 aria-disabled:pointer-events-none aria-disabled:opacity-40 '
        .($variants[$variant] ?? $variants['primary']).' '.($sizes[$size] ?? $sizes['md']);
    $iconClass = ($iconSizes[$size] ?? 'size-4').' shrink-0';
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-ui.icon :name="$icon" :class="$iconClass" />@endif
        {{ $slot }}
        @if ($iconTrailing)<x-ui.icon :name="$iconTrailing" :class="$iconClass" />@endif
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-ui.icon :name="$icon" :class="$iconClass" />@endif
        {{ $slot }}
        @if ($iconTrailing)<x-ui.icon :name="$iconTrailing" :class="$iconClass" />@endif
    </button>
@endif
