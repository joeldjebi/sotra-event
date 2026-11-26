<div class="space-y-6">
    <x-ui.alert tone="info">
        Définissez, dans chaque commune d'Abidjan, votre lieu d'embarquement habituel et le nombre de bus par offre.
        Dès qu'une ligne existe, les événements qu'ACCES EVENT vous confie sont <strong>acceptés d'office</strong> : leurs points d'embarquement
        et leur capacité (bus × places) sont repris de ces lignes. Vous pouvez ensuite ajuster la capacité d'un événement dans le plan de transport.
    </x-ui.alert>

    <x-ui.card>
        <form wire:submit="saveSeats">
            <h2 class="text-title-3">Places par bus</h2>
            <p class="text-[13px] text-ink-secondary">Capacité d'un bus de chaque offre. Capacité d'une offre pour un événement = bus des communes desservies × places par bus.</p>
            <div class="mt-4 grid gap-4 sm:grid-cols-3">
                @foreach ($offers as $offer)
                    <x-ui.input wire:key="seats-{{ $offer->value }}" :label="$offer->label()" name="seats.{{ $offer->value }}" type="number" min="10" max="200" wire:model="seats.{{ $offer->value }}" :disabled="! $this->canManage()" />
                @endforeach
            </div>
            @if ($this->canManage())
                <div class="mt-4 flex justify-end"><x-ui.button size="sm">Enregistrer les places</x-ui.button></div>
            @endif
        </form>
    </x-ui.card>

    <x-ui.table>
        <x-slot:head>
            <th>Commune</th>
            <th>Lieu d'embarquement</th>
            @foreach ($offers as $offer)
                <th class="text-right">Bus {{ $offer->label() }}</th>
            @endforeach
            <th></th>
        </x-slot:head>
        @foreach ($communes as $code => $name)
            @php($line = $lines->get($code))
            <tr wire:key="line-{{ $code }}" class="hover:bg-fill-subtle">
                <td class="font-medium">{{ $name }} <span class="text-[12px] text-ink-tertiary">{{ $code }}</span></td>
                <td>
                    @if ($line)
                        {{ $line->place }}
                    @else
                        <x-ui.badge tone="neutral">Non desservie</x-ui.badge>
                    @endif
                </td>
                @foreach ($offers as $offer)
                    <td class="text-right tabular-nums">{{ $line ? $line->busesFor($offer) : '—' }}</td>
                @endforeach
                <td class="text-right whitespace-nowrap">
                    @if ($this->canManage())
                        @if ($line)
                            <x-ui.button variant="ghost" size="sm" type="button" wire:click="delete('{{ $code }}')" wire:confirm="Supprimer la ligne de {{ $name }} ?">Supprimer</x-ui.button>
                        @endif
                        <x-ui.button variant="secondary" size="sm" type="button" wire:click="edit('{{ $code }}')">{{ $line ? 'Modifier' : 'Définir' }}</x-ui.button>
                    @endif
                </td>
            </tr>
        @endforeach
        <x-slot:footer>
            <div class="flex flex-wrap justify-end gap-x-6 gap-y-1 text-[13px] text-ink-secondary">
                <span>{{ $lines->count() }} / {{ count($communes) }} communes desservies</span>
                @foreach ($offers as $offer)
                    <span>{{ $offer->label() }} : <strong class="tabular-nums text-ink">{{ $totals[$offer->value] }}</strong> bus</span>
                @endforeach
            </div>
        </x-slot:footer>
    </x-ui.table>

    <x-ui.sheet model="showForm" width="max-w-2xl" :title="'Ligne · '.($communes[$commune_code] ?? '')" subtitle="Lieu de montée habituel et bus engagés par offre dans cette commune.">
        <form id="line-form" wire:submit="save" class="space-y-4">
            <x-ui.input label="Lieu d'embarquement" name="place" wire:model="place" placeholder="Gare Siporex" />
            <x-ui.location-picker lat="latitude" lng="longitude" place="place" />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input label="Latitude" name="latitude" wire:model="latitude" inputmode="decimal" />
                <x-ui.input label="Longitude" name="longitude" wire:model="longitude" inputmode="decimal" />
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                @foreach ($offers as $offer)
                    <x-ui.input wire:key="buses-{{ $offer->value }}" label="Bus {{ $offer->label() }}" name="buses.{{ $offer->value }}" type="number" min="0" max="200" wire:model="buses.{{ $offer->value }}" />
                @endforeach
            </div>
            <p class="text-[12px] text-ink-tertiary">0 bus : l'offre n'est pas assurée depuis cette commune.</p>
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" type="button" x-on:click="open = false">Annuler</x-ui.button>
            <x-ui.button form="line-form">Enregistrer</x-ui.button>
        </x-slot:footer>
    </x-ui.sheet>
</div>
