<x-layouts.app :title="'Bonjour, '.Str::before(auth()->user()->name, ' ')" :subtitle="$company?->name">
    <div class="grid grid-cols-3 gap-3 sm:gap-4">
        @foreach ($staffCounts as $label => $count)
            <x-ui.kpi wire:key="staff-{{ $loop->index }}" :label="$label.'s'" :value="$count" hint="dans votre compagnie" />
        @endforeach
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <x-ui.card padding="p-0">
            <div class="flex items-center justify-between px-6 pt-5 pb-3.5">
                <h2 class="text-headline">Événements proposés à valider</h2>
                @can('proposed-events.respond')
                    <a href="{{ route('company.proposed-events') }}" class="inline-flex items-center gap-0.5 text-[14px] font-medium text-brand-text hover:opacity-70">Tout voir<x-ui.icon name="chevron-right" class="size-3.5" stroke-width="2.2" /></a>
                @endcan
            </div>
            <ul class="ui-list border-t border-separator">
                @forelse ($pendingEvents as $event)
                    <li wire:key="pending-{{ $event->id }}" class="flex items-center justify-between gap-3 px-6 py-3.5">
                        <div class="min-w-0">
                            <p class="truncate font-medium">{{ $event->name }}</p>
                            <p class="text-[13px] text-ink-secondary">{{ $event->venue->name }} · {{ $event->starts_at->translatedFormat('j M Y') }}
                                @if ($event->estimated_passes) · {{ number_format($event->estimated_passes, 0, ',', ' ') }} Pass estimés @endif</p>
                        </div>
                        <x-ui.badge :tone="$event->status->tone()">{{ $event->status->label() }}</x-ui.badge>
                    </li>
                @empty
                    <li><x-ui.empty-state icon="calendar" title="Rien à valider" description="Les propositions d'ACCES EVENT apparaîtront ici." /></li>
                @endforelse
            </ul>
        </x-ui.card>

        <x-ui.card padding="p-0">
            <div class="px-6 pt-5 pb-3.5"><h2 class="text-headline">Prochains événements</h2></div>
            <ul class="ui-list border-t border-separator" style="--list-inset: 5.5rem">
                @forelse ($upcomingEvents as $event)
                    <li wire:key="upcoming-{{ $event->id }}" class="flex items-center gap-4 px-6 py-3.5">
                        <div class="grid w-12 shrink-0 place-items-center gap-0.5 rounded-[12px] bg-surface py-1.5 shadow-[var(--shadow-card)] ring-1 ring-hairline">
                            <span class="font-rounded text-[20px] leading-none font-semibold tabular-nums">{{ $event->starts_at->format('d') }}</span>
                            <span class="text-[10px] font-bold tracking-[0.04em] text-brand-text uppercase">{{ $event->starts_at->translatedFormat('M') }}</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium">{{ $event->name }}</p>
                            <p class="text-[13px] text-ink-secondary">{{ $event->venue->name }}</p>
                        </div>
                        <x-ui.badge :tone="$event->status->tone()">{{ $event->status->label() }}</x-ui.badge>
                    </li>
                @empty
                    <li><x-ui.empty-state icon="map" title="Aucun événement à venir" /></li>
                @endforelse
            </ul>
        </x-ui.card>
    </div>
</x-layouts.app>
