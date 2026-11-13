@props(['change', 'goodWhenUp' => true, 'against' => 'vs période précédente'])
@php
    // Variation signée par rapport à la période précédente ; couleur = sens × « la hausse est-elle bonne ? ».
    $direction = $change === null ? 0 : ($change > 0.0005 ? 1 : ($change < -0.0005 ? -1 : 0));
    $good = $direction === 0 ? null : (($direction > 0) === $goodWhenUp);
@endphp
<p {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-x-1.5 text-footnote']) }}>
    @if ($change === null)
        <span class="font-medium text-ink-secondary">Nouveau</span>
        <span class="text-ink-tertiary">rien sur la période précédente</span>
    @else
        <span @class(['inline-flex items-center gap-0.5 font-semibold tabular-nums', 'text-success' => $good === true, 'text-danger' => $good === false, 'text-ink-secondary' => $good === null])>
            @if ($direction !== 0)
                <svg class="size-2.5" viewBox="0 0 12 12" aria-hidden="true"><path d="{{ $direction > 0 ? 'M6 2 11 9H1z' : 'M6 10 1 3h10z' }}" fill="currentColor" /></svg>
                <span class="sr-only">{{ $direction > 0 ? 'En hausse' : 'En baisse' }}</span>
            @endif
            {{ \App\Support\Amount::percentChange($change) }}
        </span>
        <span class="text-ink-tertiary">{{ $against }}</span>
    @endif
</p>
