<x-layouts.app :title="'Bonjour, '.Str::before(auth()->user()->name, ' ')" :subtitle="$company?->name">
    <div class="grid gap-4 sm:grid-cols-3">
        @foreach ($staffCounts as $label => $count)
            <x-ui.kpi wire:key="staff-{{ $loop->index }}" :label="$label.'s'" :value="$count" hint="dans votre compagnie" />
        @endforeach
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <x-ui.card padding="p-0">
            <div class="flex items-center justify-between px-6 pt-5 pb-3">
                <h2 class="text-[17px] font-semibold tracking-tight">Événements proposés à valider</h2>
                @can('proposed-events.respond')
                    <a href="{{ route('company.proposed-events') }}" class="text-[14px] font-medium text-brand hover:underline">Tout voir</a>
                @endcan
            </div>
            <ul class="divide-y divide-hairline border-t border-hairline">
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
            <div class="px-6 pt-5 pb-3"><h2 class="text-[17px] font-semibold tracking-tight">Prochains événements</h2></div>
            <ul class="divide-y divide-hairline border-t border-hairline">
                @forelse ($upcomingEvents as $event)
                    <li wire:key="upcoming-{{ $event->id }}" class="flex items-center gap-4 px-6 py-3.5">
                        <div class="grid w-12 shrink-0 place-items-center rounded-xl bg-brand-soft py-1.5 text-brand">
                            <span class="text-[17px] leading-none font-semibold">{{ $event->starts_at->format('d') }}</span>
                            <span class="text-[11px] font-semibold uppercase">{{ $event->starts_at->translatedFormat('M') }}</span>
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
