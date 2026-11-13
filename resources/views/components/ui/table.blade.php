@props(['compact' => false])
{{--
    Tableau léger dans une carte : en-têtes discrets, lignes aérées, séparateurs indentés façon iOS.
    Colonnes via <x-slot:head> (th) et lignes dans le slot (tr). Pied facultatif via <x-slot:footer>.
--}}
<x-ui.card padding="p-0" {{ $attributes->merge(['class' => 'overflow-hidden']) }}>
    <div class="overflow-x-auto">
        <table @class(['ui-table', 'ui-table--compact' => $compact])>
            <thead>
                <tr>{{ $head }}</tr>
            </thead>
            <tbody>
                {{ $slot }}
            </tbody>
        </table>
    </div>
    @isset($footer)
        <div class="border-t border-separator px-5 py-3">{{ $footer }}</div>
    @endisset
</x-ui.card>
