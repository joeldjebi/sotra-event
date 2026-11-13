<div wire:poll.15s>
    <h1 class="text-[34px] leading-tight font-bold tracking-tight">Mon trajet</h1>

    @if (! $customer)
        <x-ui.card class="mt-6" padding="p-0">
            <x-ui.empty-state icon="map" title="Suivez votre bus en direct" description="Connectez-vous avec votre numéro pour voir l'avancée de votre bus le jour J.">
                <x-ui.button :href="route('site.login')">Se connecter</x-ui.button>
            </x-ui.empty-state>
        </x-ui.card>
    @elseif (! $pass)
        <x-ui.card class="mt-6" padding="p-0">
            <x-ui.empty-state icon="ticket" title="Aucun trajet à venir" description="Réservez votre bus pour le prochain événement.">
                <x-ui.button :href="route('home')">Voir les événements</x-ui.button>
            </x-ui.empty-state>
        </x-ui.card>
    @else
        <p class="mt-1 text-[15px] text-ink-secondary">{{ $pass->event->name }} · {{ $pass->boardingPoint->commune }} · Pass {{ $pass->code }}</p>

        @if ($passes->count() > 1)
            <div class="mt-4">
                <x-ui.select label="Pass" name="code" wire:model.live="code"
                             :options="$passes->mapWithKeys(fn ($item) => [$item->code => $item->code.' · '.$item->event->name])->all()" />
            </div>
        @endif

        @if ($trip['live'] && $trip['live']['eta_minutes'] !== null)
            <div class="mt-5 rounded-[22px] bg-marine-900 p-5 text-white">
                <p class="text-[13px] font-semibold tracking-wide text-orange-300 uppercase">Bus {{ $trip['live']['bus'] }} · {{ $trip['live']['status_label'] }}</p>
                <p class="mt-1 text-[28px] font-semibold tracking-tight">≈ {{ $trip['live']['eta_minutes'] }} min</p>
                <p class="text-[14px] text-white/70">avant l'arrivée · {{ $trip['live']['destination'] }}</p>
            </div>
        @endif

        <x-ui.map class="mt-5" :data="$map" label="Position de votre bus" :height="260" />

        <ol class="mt-6 space-y-3">
            @foreach ($trip['steps'] as $step)
                <li wire:key="step-{{ $loop->index }}" @class([
                    'flex gap-4 rounded-[18px] bg-surface p-4 ring-1',
                    'ring-2 ring-brand' => $step['current'],
                    'ring-hairline' => ! $step['current'],
                ])>
                    <span @class([
                        'mt-1 size-3 shrink-0 rounded-full',
                        'bg-success' => $step['done'],
                        'bg-brand' => $step['current'],
                        'bg-fill-strong' => ! $step['done'] && ! $step['current'],
                    ])></span>
                    <div>
                        <p class="text-[17px] font-semibold tabular-nums">{{ \Illuminate\Support\Carbon::parse($step['at'])->format('H\hi') }}</p>
                        <p @class(['text-[15px]', 'text-ink-secondary' => ! $step['current']])>{{ $step['label'] }}</p>
                    </div>
                </li>
            @endforeach
        </ol>
    @endif
</div>
