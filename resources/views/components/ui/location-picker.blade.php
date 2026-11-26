@props(['lat' => 'latitude', 'lng' => 'longitude', 'place' => null, 'height' => 260, 'label' => 'Position sur la carte'])
{{--
    Sélecteur de lieu (resources/js/map.js, « locationPicker ») : recherche par nom dans le grand Abidjan, clic sur la
    carte ou marqueur déplaçable. Écrit les coordonnées dans les propriétés Livewire $lat et $lng, et le nom trouvé
    dans $place s'il est vide. Protégé du morphing Livewire (wire:ignore) : l'état passe uniquement par $wire.
    Usage : <x-ui.location-picker lat="latitude" lng="longitude" place="place" />
--}}
<div class="space-y-1.5">
    <p class="px-0.5 text-footnote font-medium text-ink-secondary">{{ $label }}</p>
    <div wire:ignore
         x-data="locationPicker({{ Js::from(['lat' => $lat, 'lng' => $lng, 'place' => $place, 'searchUrl' => route('geocode')]) }})"
         {{ $attributes->merge(['class' => 'space-y-2']) }}>
        <div class="relative">
            <div class="flex gap-2">
                <input type="search" x-model="query" x-on:keydown.enter.prevent="search()"
                       placeholder="Rechercher : gare, carrefour, quartier…" aria-label="Rechercher un lieu"
                       class="ui-field h-10 min-w-0 flex-1 px-3.5">
                <x-ui.button type="button" variant="secondary" size="sm" class="h-10" x-on:click="search()" x-bind:disabled="searching">
                    <span x-show="!searching">Rechercher</span>
                    <span x-cloak x-show="searching">Recherche…</span>
                </x-ui.button>
            </div>
            <ul x-cloak x-show="results.length" x-on:click.outside="results = []"
                class="elevated absolute inset-x-0 top-11 z-[1000] max-h-64 overflow-y-auto rounded-xl bg-surface py-1 shadow-[var(--shadow-float)] ring-1 ring-hairline">
                <template x-for="(result, index) in results" :key="index">
                    <li>
                        <button type="button" x-on:click="pick(result)" class="block w-full px-3.5 py-2 text-left hover:bg-fill-subtle">
                            <span class="block text-[14px] font-medium" x-text="result.name"></span>
                            <span class="block truncate text-[12px] text-ink-tertiary" x-text="result.label"></span>
                        </button>
                    </li>
                </template>
            </ul>
        </div>
        <p x-cloak x-show="error" x-text="error" class="px-1 text-footnote text-danger"></p>
        <div class="relative overflow-hidden rounded-[var(--radius-card)] ring-1 ring-hairline">
            <div data-map-canvas role="application" aria-label="{{ $label }} : cliquez pour placer le point" class="z-0" style="height: {{ (int) $height }}px"></div>
        </div>
        <p class="px-1 text-footnote text-ink-tertiary">Cliquez sur la carte ou déplacez le marqueur pour ajuster le point exact.</p>
    </div>
</div>
