@props(['config', 'label', 'height' => 260])
{{--
    Graphique Chart.js (resources/js/charts.js) : la configuration est sérialisée dans data-chart et relue à chaque
    rendu Livewire ; le canvas est protégé du morphing par wire:ignore. Couleurs = jetons CSS (series.*.color).
    config : labels, series [{name, values, color}], titles (info-bulle), unit, format, details, horizontal.
--}}
<div x-data="chart" data-chart="{{ json_encode($config, JSON_THROW_ON_ERROR) }}" {{ $attributes->merge(['class' => 'relative']) }}>
    <div wire:ignore class="relative" style="height: {{ (int) $height }}px">
        <canvas role="img" aria-label="{{ $label }}"></canvas>
    </div>
</div>
