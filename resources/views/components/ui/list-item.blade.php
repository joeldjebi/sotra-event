@props(['title' => null, 'subtitle' => null, 'icon' => null, 'iconTone' => 'brand', 'href' => null, 'chevron' => null])
@php
    // Cellule iOS : pastille d'icône colorée, titre, sous-titre, accessoire (slot « trailing ») et chevron.
    $iconTones = [
        'brand' => 'bg-brand text-white',
        'marine' => 'bg-marine-900 text-white',
        'success' => 'bg-[#34c759] text-white',
        'warning' => 'bg-[#ff9f0a] text-white',
        'danger' => 'bg-[#ff3b30] text-white',
        'gray' => 'bg-[#8e8e93] text-white',
    ];
    $chevron ??= $href !== null;
    $rowClasses = 'flex min-h-11 items-center gap-3.5 px-5 py-3';
@endphp
<li {{ $attributes }}>
    @if ($href)<a href="{{ $href }}" class="{{ $rowClasses }} transition-colors hover:bg-fill-subtle active:bg-fill">@else<div class="{{ $rowClasses }}">@endif
        @if ($icon)
            <span class="grid size-[30px] shrink-0 place-items-center rounded-[8px] {{ $iconTones[$iconTone] ?? $iconTones['brand'] }}">
                <x-ui.icon :name="$icon" class="size-[18px]" stroke-width="1.9" />
            </span>
        @endif
        @isset($leading){{ $leading }}@endisset
        <div class="min-w-0 flex-1">
            @if ($title !== null)
                <p class="truncate text-[15px] font-medium tracking-[-0.01em]">{{ $title }}</p>
            @endif
            @if ($subtitle !== null)
                <p class="truncate text-footnote text-ink-secondary">{{ $subtitle }}</p>
            @endif
            {{ $slot }}
        </div>
        @isset($trailing)<div class="flex shrink-0 items-center gap-2">{{ $trailing }}</div>@endisset
        @if ($chevron)
            <x-ui.icon name="chevron-right" class="size-4 shrink-0 text-ink-tertiary/70" stroke-width="2.2" />
        @endif
    @if ($href)</a>@else</div>@endif
</li>
