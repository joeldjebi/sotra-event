{{-- Tableau dans une carte. Colonnes via <x-slot:head> (th) et lignes dans le slot (tr). --}}
<x-ui.card padding="p-0" {{ $attributes->merge(['class' => 'overflow-hidden']) }}>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-[14px]">
            <thead class="border-b border-hairline bg-canvas/60 text-[12px] font-medium text-ink-secondary">
                <tr class="[&>th]:px-5 [&>th]:py-3 [&>th]:font-medium [&>th]:whitespace-nowrap">{{ $head }}</tr>
            </thead>
            <tbody class="divide-y divide-hairline [&_td]:px-5 [&_td]:py-3.5 [&_td]:align-middle">
                {{ $slot }}
            </tbody>
        </table>
    </div>
    @isset($footer)
        <div class="border-t border-hairline px-5 py-3">{{ $footer }}</div>
    @endisset
</x-ui.card>
