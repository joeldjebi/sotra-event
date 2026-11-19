<div>
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <x-ui.search wire:model.live.debounce.300ms="search" placeholder="Rechercher une demande" />
        <x-ui.button wire:click="create" type="button" icon="sparkles">Proposer un événement</x-ui.button>
    </div>

    <x-ui.table>
        <x-slot:head>
            <th>Événement</th>
            <th>Date</th>
            <th>Lieu</th>
            <th class="text-right">Public attendu</th>
            <th>Statut</th>
            <th></th>
        </x-slot:head>

        @forelse ($requests as $request)
            <tr wire:key="request-{{ $request->id }}" class="hover:bg-fill-subtle">
                <td>
                    <p class="font-medium">{{ $request->name }}</p>
                    <p class="text-[12px] text-ink-secondary">Envoyée le {{ $request->created_at->format('d/m/Y') }}</p>
                </td>
                <td class="whitespace-nowrap">{{ $request->starts_at->translatedFormat('d M Y · H\hi') }}</td>
                <td class="text-ink-secondary">{{ $request->venueLabel() }}</td>
                <td class="text-right tabular-nums">{{ $request->expected_attendance ? number_format($request->expected_attendance, 0, ',', ' ') : '—' }}</td>
                <td>
                    <x-ui.badge :tone="$request->status->tone()">{{ $request->status->label() }}</x-ui.badge>
                    @if ($request->review_note)
                        <p class="mt-1 max-w-64 text-[12px] text-ink-secondary">{{ $request->review_note }}</p>
                    @endif
                </td>
                <td class="text-right whitespace-nowrap">
                    @if ($request->isPending())
                        <x-ui.button variant="secondary" size="sm" type="button" wire:click="edit({{ $request->id }})">Modifier</x-ui.button>
                        <x-ui.button variant="danger-tinted" size="sm" type="button" class="ml-1" wire:click="withdraw({{ $request->id }})" wire:confirm="Retirer cette demande ?">Retirer</x-ui.button>
                    @elseif ($request->event)
                        <span class="text-[13px] text-ink-secondary">Événement créé</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6"><x-ui.empty-state icon="calendar" title="Aucune demande" description="Proposez vos événements : ACCES EVENT organise le transport avec une compagnie partenaire." /></td></tr>
        @endforelse

        @if ($requests->hasPages())
            <x-slot:footer>{{ $requests->links() }}</x-slot:footer>
        @endif
    </x-ui.table>

    <x-ui.sheet model="showForm" :title="$editingId ? 'Modifier la demande' : 'Proposer un événement'">
        <form id="request-form" wire:submit="save" class="space-y-4">
            <x-ui.input label="Nom de l'événement" name="name" wire:model="name" />
            <x-ui.textarea label="Description (facultatif)" name="description" wire:model="description" />
            <x-ui.select label="Lieu" name="venue_id" wire:model.live="venue_id" :options="$venues" placeholder="Autre lieu (à préciser)" />
            @if ($venue_id === null)
                <x-ui.input label="Lieu, s'il n'est pas dans la liste" name="venue_name" wire:model="venue_name" placeholder="Nom et commune" />
            @endif
            <div class="grid gap-4 sm:grid-cols-3">
                <x-ui.input label="Date" name="date" type="date" wire:model="date" />
                <x-ui.input label="Début" name="start_time" type="time" wire:model="start_time" />
                <x-ui.input label="Fin prévue (facultatif)" name="end_time" type="time" wire:model="end_time" />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input label="Public attendu" name="expected_attendance" type="number" min="1" wire:model="expected_attendance" />
                <x-ui.input label="Pass transport estimés" name="estimated_passes" type="number" min="1" wire:model="estimated_passes" />
            </div>
            <x-ui.textarea label="Communes de départ souhaitées" name="desired_communes" rows="2" wire:model="desired_communes" placeholder="Ex. Yopougon, Abobo, Cocody" />
            <p class="text-[13px] text-ink-secondary">ACCES EVENT choisit ensuite la compagnie de transport, les points d'embarquement et la grille tarifaire.</p>
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" type="button" x-on:click="open = false">Annuler</x-ui.button>
            <x-ui.button form="request-form">{{ $editingId ? 'Enregistrer' : 'Envoyer la demande' }}</x-ui.button>
        </x-slot:footer>
    </x-ui.sheet>
</div>
