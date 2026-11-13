@props(['label' => null])
{{--
    Contrôle segmenté iOS/macOS. Les segments sont des <x-ui.segment :active="…" wire:click="…">.
    Usage : <x-ui.segmented label="Filtrer par espace"> <x-ui.segment :active="$f === ''" wire:click="$set('f', '')">Tous</x-ui.segment> … </x-ui.segmented>
--}}
<div role="group" @if ($label) aria-label="{{ $label }}" @endif {{ $attributes->merge(['class' => 'ui-segmented']) }}>
    {{ $slot }}
</div>
