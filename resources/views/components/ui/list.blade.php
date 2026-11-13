@props(['inset' => null, 'card' => true])
{{--
    Liste « inset grouped » façon Réglages iOS : cellules empilées, séparateurs fins indentés.
    inset : retrait du séparateur (ex. "4.5rem" pour s'aligner sur le texte après une icône).
    :card="false" quand la liste est déjà dans une <x-ui.card>.
    Usage : <x-ui.list> <x-ui.list-item title="…" subtitle="…" icon="bus" :href="…" /> … </x-ui.list>
--}}
<ul {{ $attributes->merge(['class' => 'ui-list'.($card ? ' overflow-hidden rounded-[var(--radius-card)] bg-surface shadow-[var(--shadow-card)] ring-1 ring-hairline' : '')]) }}
    @if ($inset) style="--list-inset: {{ $inset }}" @endif>
    {{ $slot }}
</ul>
