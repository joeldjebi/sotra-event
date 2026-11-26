<div class="space-y-6">
    <x-ui.alert tone="info">
        Définissez vos lignes habituelles : dans chaque commune, un ou plusieurs lieux d'embarquement, chacun vers une destination
        (un lieu d'événement, ou tous les lieux) avec son nombre de bus par offre. Un événement dont le lieu est desservi par vos lignes
        est <strong>accepté d'office</strong> : ses points d'embarquement et sa capacité (bus × places) sont repris de ces lignes. Vous pouvez ensuite ajuster la capacité d'un événement dans le plan de transport.
    </x-ui.alert>

    <x-ui.card>
        <form wire:submit="saveSeats">
            <h2 class="text-title-3">Places par bus</h2>
            <p class="text-[13px] text-ink-secondary">Capacité d'un bus de chaque offre. Capacité d'une offre pour un événement = bus des lignes reprises × places par bus.</p>
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
            <th>Destination</th>
            @foreach ($offers as $offer)
                <th class="text-right">Bus {{ $offer->label() }}</th>
            @endforeach
            <th></th>
        </x-slot:head>
        @foreach ($communes as $code => $name)
            @php($communeLines = $linesByCommune->get($code, collect()))
            @forelse ($communeLines as $line)
                <tr wire:key="line-{{ $line->id }}" class="hover:bg-fill-subtle">
                    @if ($loop->first)
                        <td rowspan="{{ $communeLines->count() }}" class="!align-top font-medium">
                            {{ $name }} <span class="text-[12px] text-ink-tertiary">{{ $code }}</span>
                            @if ($this->canManage())
                                <button type="button" wire:click="create('{{ $code }}')" class="mt-1 block text-[12px] font-medium text-brand-text hover:underline">+ Ajouter un lieu</button>
                            @endif
                        </td>
                    @endif
                    <td>{{ $line->place }}</td>
                    <td class="text-ink-secondary">{{ $line->venue?->name ?? 'Tous les lieux' }}</td>
                    @foreach ($offers as $offer)
                        <td class="text-right tabular-nums">{{ $line->busesFor($offer) }}</td>
                    @endforeach
                    <td class="text-right whitespace-nowrap">
                        @if ($this->canManage())
                            <x-ui.button variant="ghost" size="sm" type="button" wire:click="delete({{ $line->id }})" wire:confirm="Supprimer la ligne {{ $line->place }} ?">Supprimer</x-ui.button>
                            <x-ui.button variant="secondary" size="sm" type="button" wire:click="edit({{ $line->id }})">Modifier</x-ui.button>
                        @endif
                    </td>
                </tr>
            @empty
                <tr wire:key="commune-{{ $code }}" class="hover:bg-fill-subtle">
                    <td class="font-medium">{{ $name }} <span class="text-[12px] text-ink-tertiary">{{ $code }}</span></td>
                    <td colspan="{{ 2 + count($offers) }}"><x-ui.badge tone="neutral">Non desservie</x-ui.badge></td>
                    <td class="text-right">
                        @if ($this->canManage())
                            <x-ui.button variant="secondary" size="sm" type="button" wire:click="create('{{ $code }}')">Ajouter un lieu</x-ui.button>
                        @endif
                    </td>
                </tr>
            @endforelse
        @endforeach
        <x-slot:footer>
            <div class="flex flex-wrap justify-end gap-x-6 gap-y-1 text-[13px] text-ink-secondary">
                <span>{{ $lineCount }} ligne(s) · {{ $linesByCommune->count() }} / {{ count($communes) }} communes desservies</span>
                @foreach ($offers as $offer)
                    <span>{{ $offer->label() }} : <strong class="tabular-nums text-ink">{{ $totals[$offer->value] }}</strong> bus</span>
                @endforeach
            </div>
        </x-slot:footer>
    </x-ui.table>

    <x-ui.sheet model="showForm" width="max-w-2xl" :title="$editingId ? 'Modifier la ligne' : 'Nouvelle ligne'" subtitle="Lieu de montée habituel, destination et bus engagés par offre.">
        <form id="line-form" wire:submit="save" class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.select label="Commune" name="commune_code" wire:model="commune_code" :options="$communes" placeholder="Choisir" />
                <x-ui.select label="Destination" name="venue_id" wire:model="venue_id" :options="$venues" placeholder="Tous les lieux" hint="Lieu d'événement desservi par cette ligne." />
            </div>
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
