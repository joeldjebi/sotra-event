<x-layouts.app :title="$event?->name ?? 'Nouvel événement'" subtitle="Événements">
    {{-- Étapes en contrôle segmenté (capsule), l'étape courante en surbrillance. --}}
    <nav class="ui-segmented -mt-1 mb-8 !flex w-full !rounded-full !p-1 sm:!inline-flex sm:w-auto" aria-label="Étapes">
        @foreach ($steps as $number => $label)
            @php($reachable = $event !== null || $number === 1)
            <a @if ($reachable) href="{{ $event ? route('admin.events.edit', ['event' => $event, 'etape' => $number]) : route('admin.events.create') }}" @endif
               @if ($number === $step) aria-current="step" @endif
               @class([
                   'ui-segment h-8 shrink-0 !rounded-full !px-3.5',
                   'cursor-not-allowed text-ink-tertiary' => ! $reachable,
               ])>
                <span @class(['grid size-5 place-items-center rounded-full text-[11px] font-semibold tabular-nums', 'bg-brand text-white' => $number === $step, 'bg-fill-strong text-ink-secondary' => $number !== $step])>{{ $number }}</span>
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
