@props(['active' => false, 'href' => null])
{{-- Segment d'un <x-ui.segmented>. Bouton par défaut, lien si href est fourni (aria-current sur l'actif). --}}
@if ($href)
    <a href="{{ $href }}" @if ($active) aria-current="page" @endif {{ $attributes->merge(['class' => 'ui-segment']) }}>{{ $slot }}</a>
@else
    <button type="button" aria-pressed="{{ $active ? 'true' : 'false' }}" {{ $attributes->merge(['class' => 'ui-segment']) }}>{{ $slot }}</button>
@endif
