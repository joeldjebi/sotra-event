<x-layouts.app :title="$event?->name ?? 'Nouvel événement'" subtitle="Événements">
    <nav class="-mt-4 mb-8 flex flex-wrap gap-2" aria-label="Étapes">
        @foreach ($steps as $number => $label)
            @php($reachable = $event !== null || $number === 1)
            <a @if ($reachable) href="{{ $event ? route('admin.events.edit', ['event' => $event, 'etape' => $number]) : route('admin.events.create') }}" @endif
               @class([
                   'inline-flex h-9 items-center gap-2 rounded-full px-4 text-[13px] font-medium transition',
                   'bg-marine-900 text-white' => $number === $step,
                   'bg-surface text-ink ring-1 ring-hairline hover:bg-canvas' => $number !== $step && $reachable,
                   'cursor-not-allowed bg-fill text-ink-tertiary' => ! $reachable,
               ])>
                <span @class(['grid size-5 place-items-center rounded-full text-[11px]', 'bg-white/20' => $number === $step, 'bg-fill' => $number !== $step])>{{ $number }}</span>
                {{ $label }}
            </a>
        @endforeach
    </nav>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        <div class="min-w-0">
            @switch($step)
                @case(1) @livewire('admin.events.information-step', ['event' => $event]) @break
                @case(2) @livewire('admin.events.boarding-points-step', ['event' => $event]) @break
                @case(3) @livewire('admin.events.pricing-step', ['event' => $event]) @break
                @case(4) @livewire('admin.events.publication-step', ['event' => $event]) @break
            @endswitch
        </div>

        @if ($event)
            <aside class="min-w-0">
                @livewire('admin.events.status-panel', ['event' => $event, 'step' => $step])
            </aside>
        @endif
    </div>
</x-layouts.app>
