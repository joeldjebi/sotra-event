<div class="space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <x-ui.segmented label="Statut">
            <x-ui.segment :active="$status === ''" wire:click="$set('status', '')">Toutes</x-ui.segment>
            @foreach ($statuses as $value => $label)
                <x-ui.segment wire:key="status-{{ $value }}" :active="$status === $value" wire:click="$set('status', '{{ $value }}')">{{ $label }}</x-ui.segment>
            @endforeach
        </x-ui.segmented>
        @if ($openCount > 0)
            <x-ui.badge tone="danger" class="px-3 py-1 text-[13px]">{{ $openCount }} en cours</x-ui.badge>
        @endif
    </div>

    <x-ui.table>
        <x-slot:head>
            <th>Signalée</th>
            <th>Bus</th>
            <th>Panne</th>
            <th class="text-right">Passagers</th>
            <th>Équipe</th>
            <th>Statut</th>
            <th>Résultat</th>
            <th></th>
        </x-slot:head>
        @forelse ($alerts as $alert)
            <tr wire:key="breakdown-{{ $alert->id }}">
                <td class="whitespace-nowrap">
                    <p>{{ $alert->created_at->format('d/m H\hi') }}</p>
                    <p class="text-[12px] text-ink-secondary">{{ $alert->mission->event->name }}</p>
                </td>
                <td class="font-semibold">{{ $alert->bus ? 'Bus '.$alert->bus->number : '—' }}</td>
                <td>
                    <p>{{ $alert->type->label() }}</p>
                    <x-ui.badge :tone="$alert->urgency->value === 'urgent' ? 'danger' : 'neutral'">{{ $alert->urgency->value === 'urgent' ? 'Urgent' : 'Normal' }}</x-ui.badge>
                </td>
                <td class="text-right tabular-nums">{{ $alert->passengers_on_board }}</td>
                <td>{{ $alert->techTeam ? 'Équipe '.$alert->techTeam->code : '—' }}</td>
                <td><x-ui.badge :tone="$alert->status->tone()">{{ $alert->intervention?->step->label() ?? $alert->status->label() }}</x-ui.badge></td>
                <td class="text-[13px]">
                    @if ($alert->intervention?->result)
                        {{ $alert->intervention->result->label() }}@if ($alert->intervention->towing_requested && $alert->intervention->result->value !== 'remorquage') + remorquage @endif
                    @else
                        <span class="text-ink-tertiary">—</span>
                    @endif
                </td>
                <td class="text-right"><x-ui.button variant="secondary" size="sm" type="button" wire:click="show({{ $alert->id }})">Détails</x-ui.button></td>
            </tr>
        @empty
            <tr><td colspan="8"><x-ui.empty-state icon="wrench" title="Aucune panne" description="Les pannes signalées par les convoyeurs apparaîtront ici." /></td></tr>
        @endforelse
        @if ($alerts->hasPages())
            <x-slot:footer>{{ $alerts->links() }}</x-slot:footer>
        @endif
    </x-ui.table>

    <x-ui.sheet model="showDetails" :title="$viewing ? ($viewing->intervention?->number ?? 'Panne').' · Bus '.($viewing->bus?->number ?? '?') : 'Panne'" :subtitle="$viewing?->type->label()">
        @if ($viewing)
            @php($intervention = $viewing->intervention)
            <div class="space-y-6">
                <dl class="grid grid-cols-2 gap-4 text-[14px]">
                    <div><dt class="text-ink-secondary">Signalée</dt><dd class="font-medium">{{ $viewing->created_at->format('d/m/Y H\hi') }} · {{ $viewing->channel === 'sms' ? 'par SMS' : 'depuis l\'app' }}</dd></div>
                    <div><dt class="text-ink-secondary">Convoyeur</dt><dd class="font-medium">{{ $viewing->reporter->fullName() }} · {{ $viewing->reporter->phone }}</dd></div>
                    <div><dt class="text-ink-secondary">Passagers à bord</dt><dd class="font-medium">{{ $viewing->passengers_on_board }}</dd></div>
                    <div><dt class="text-ink-secondary">Position</dt><dd class="font-medium">
                        @if ($viewing->location)
                            <a class="text-brand hover:underline" target="_blank" rel="noopener" href="https://www.openstreetmap.org/?mlat={{ $viewing->location->getLatitude() }}&mlon={{ $viewing->location->getLongitude() }}#map=16/{{ $viewing->location->getLatitude() }}/{{ $viewing->location->getLongitude() }}">Voir sur la carte</a>
                        @else — @endif
                    </dd></div>
                </dl>
                @if ($viewing->details)
                    <p class="rounded-xl bg-fill px-4 py-3 text-[14px]">{{ $viewing->details }}</p>
                @endif

                @if ($intervention)
                    <div>
                        <p class="mb-2 text-[13px] font-semibold text-ink-secondary">Intervention · équipe {{ $viewing->techTeam?->code }}</p>
                        <ol class="space-y-1.5 text-[14px]">
                            @foreach (\App\Enums\InterventionStep::cases() as $step)
                                @php($at = $intervention->{$step->timestampColumn()})
                                <li wire:key="step-{{ $step->value }}" class="flex justify-between">
                                    <span @class(['text-ink-tertiary' => ! $at])>{{ $step->label() }}</span>
                                    <span class="tabular-nums">{{ $at?->format('H\hi') ?? '—' }}</span>
                                </li>
                            @endforeach
                        </ol>
                        @if ($intervention->on_site_at)
                            <p class="mt-2 text-[13px] text-ink-secondary">Délai d'arrivée sur place : {{ (int) $viewing->created_at->diffInMinutes($intervention->on_site_at) }} min</p>
                        @endif
                    </div>
                    @if ($intervention->result)
                        <div class="rounded-xl bg-success-soft px-4 py-3 text-[14px] text-success">
                            <p class="font-semibold">{{ $intervention->result->label() }}@if ($intervention->replacementBus) · bus {{ $intervention->replacementBus->number }}@endif</p>
                            @if ($intervention->diagnosis)<p class="mt-1">{{ $intervention->diagnosis }}</p>@endif
                            <p class="mt-1">Pièces : {{ $intervention->parts ?: 'aucune' }} · Durée : {{ $intervention->duration_min ? $intervention->duration_min.' min' : '—' }}</p>
                        </div>
                    @endif
                @endif

                @if ($viewing->photos !== [])
                    <div class="grid grid-cols-3 gap-2">
                        @foreach ($viewing->photoUrls() as $url)
                            <a href="{{ $url }}" target="_blank" wire:key="photo-{{ $loop->index }}"><img src="{{ $url }}" alt="Photo de la panne" class="aspect-square w-full rounded-xl object-cover"></a>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    </x-ui.sheet>
</div>
