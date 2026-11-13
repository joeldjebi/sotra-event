<div>
    @include('livewire.company.quality.partials.event-bar')

    <x-ui.segmented label="Filtre" class="mb-4">
        <x-ui.segment :active="$filter === ''" wire:click="$set('filter', '')">Tous</x-ui.segment>
        <x-ui.segment :active="$filter === 'commentaires'" wire:click="$set('filter', 'commentaires')">Avec commentaire</x-ui.segment>
        <x-ui.segment :active="$filter === 'faibles'" wire:click="$set('filter', 'faibles')">Notes &lt; 3</x-ui.segment>
    </x-ui.segmented>

    <x-ui.table>
        <x-slot:head>
            <th>Reçu</th>
            <th>Bus</th>
            <th class="text-right">Note</th>
            @foreach ($criteria as $label)
                <th class="hidden text-right xl:table-cell">{{ $label }}</th>
            @endforeach
            <th>Commentaire</th>
        </x-slot:head>
        @forelse ($reviews as $review)
            <tr wire:key="review-{{ $review->id }}">
                <td class="whitespace-nowrap">{{ $review->created_at->format('d/m H\hi') }}</td>
                <td class="whitespace-nowrap">
                    <p class="font-medium">{{ $review->mission?->bus ? 'Bus '.$review->mission->bus->number : '—' }}</p>
                    <p class="text-[12px] text-ink-secondary">{{ $review->mission?->boardingPoint->commune }} · {{ $review->mission?->direction->label() }}</p>
                </td>
                <td class="text-right">
                    <x-ui.badge :tone="$review->average() >= 4 ? 'success' : ($review->average() >= 3 ? 'neutral' : 'warning')">{{ number_format($review->average(), 1, ',', ' ') }} ★</x-ui.badge>
                </td>
                @foreach (array_keys($criteria) as $key)
                    <td class="hidden text-right tabular-nums xl:table-cell">{{ $review->getAttribute($key) }}</td>
                @endforeach
                <td class="max-w-80 text-[13px] text-ink-secondary">{{ $review->comment ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="9"><x-ui.empty-state icon="chat" title="Aucun avis" description="Les spectateurs donnent leur avis après leur trajet, depuis l'application ou le site." /></td></tr>
        @endforelse
        @if ($reviews->hasPages())
            <x-slot:footer>{{ $reviews->links() }}</x-slot:footer>
        @endif
    </x-ui.table>
</div>
