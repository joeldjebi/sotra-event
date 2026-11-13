@props(['values', 'label' => null, 'width' => 112, 'height' => 32])
@php
    // Mini-courbe (tendance) : trait discret, dernier point dans la couleur d'accent, sans axe ni info-bulle.
    $values = array_values(array_map('intval', $values));
    $count = count($values);
    $max = max(1, ...($values ?: [0]));
    $pad = 4;
    $points = [];
    foreach ($values as $index => $value) {
        $x = $count > 1 ? $pad + ($width - 2 * $pad) * $index / ($count - 1) : $width / 2;
        $y = $height - $pad - ($height - 2 * $pad) * $value / $max;
        $points[] = round($x, 1).','.round($y, 1);
    }
    [$lastX, $lastY] = $points === [] ? [$width / 2, $height - $pad] : explode(',', end($points));
@endphp
@if ($count > 1)
    <svg {{ $attributes->merge(['class' => 'shrink-0 overflow-visible']) }} width="{{ $width }}" height="{{ $height }}" viewBox="0 0 {{ $width }} {{ $height }}"
         @if ($label) role="img" aria-label="{{ $label }}" @else aria-hidden="true" @endif>
        <polyline points="{{ implode(' ', $points) }}" fill="none" stroke="var(--color-ink-tertiary)" stroke-opacity="0.6" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round" />
        <circle cx="{{ $lastX }}" cy="{{ $lastY }}" r="3.5" fill="var(--color-brand)" stroke="var(--color-surface)" stroke-width="2" />
    </svg>
@endif
