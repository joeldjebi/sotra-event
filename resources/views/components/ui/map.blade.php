@props(['data', 'label', 'height' => 420])
{{--
    Carte Leaflet (resources/js/map.js) : données sérialisées dans data-map et relues à chaque rendu Livewire ;
    la carte est protégée du morphing par wire:ignore.
    data : center [lat, lng], zoom, fit, markers [{id, lat, lng, label, tone}], zones [{lat, lng, radius, label}].
--}}
<div x-data="liveMap" data-map="{{ json_encode($data, JSON_THROW_ON_ERROR) }}" {{ $attributes->merge(['class' => 'relative overflow-hidden rounded-[var(--radius-card)] ring-1 ring-hairline']) }}>
    <div wire:ignore data-map-canvas role="img" aria-label="{{ $label }}" class="z-0" style="height: {{ (int) $height }}px"></div>
</div>
