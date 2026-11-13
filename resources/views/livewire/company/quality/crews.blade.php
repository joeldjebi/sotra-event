<div>
    @include('livewire.company.quality.partials.event-bar')
    <x-ui.card padding="p-0">
        @include('livewire.company.quality.partials.crews-table', ['crews' => $crews])
    </x-ui.card>
    <p class="mt-3 px-1 text-[13px] text-ink-tertiary">La note d'un chauffeur ou d'un convoyeur est la moyenne des avis des passagers de ses bus sur l'événement.</p>
</div>
