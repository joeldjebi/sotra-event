@props(['segments', 'label'])
{{--
    Barre segmentée fine (part d'un tout) : aucun texte sur la barre, les chiffres sont dans la liste voisine
    (une pastille de la même couleur sur chaque ligne). 2 px d'écart entre segments, extrémités arrondies.
    segments : [['label' => …, 'value' => int, 'color' => jeton sans le préfixe « --color- »]].
--}}
@php
    $total = array_sum(array_column($segments, 'value'));
    $visible = array_values(array_filter($segments, fn ($segment) => $segment['value'] > 0));
    $description = collect($visible)->map(fn ($s) => $s['label'].' '.\App\Support\Amount::percent($total > 0 ? $s['value'] / $total : 0))->implode(', ');
@endphp
<div role="img" aria-label="{{ $label }}{{ $description !== '' ? ' : '.$description : '' }}"
     {{ $attributes->merge(['class' => 'flex h-2 w-full gap-0.5']) }}>
    @if ($total === 0)
        <span class="h-full w-full rounded-full bg-fill"></span>
    @else
        @foreach ($visible as $segment)
            <span class="h-full min-w-1 first:rounded-l-full last:rounded-r-full" style="flex: {{ $segment['value'] }} 1 0%; background-color: var(--color-{{ $segment['color'] }})"></span>
        @endforeach
    @endif
</div>
