<div class="space-y-4 xl:sticky xl:top-8">
    <x-ui.card padding="p-5">
        <div class="flex items-center justify-between">
            <p class="text-headline">Statut</p>
            <x-ui.badge :tone="$event->status->tone()">{{ $event->status->label() }}</x-ui.badge>
        </div>
        <p class="mt-2 text-footnote text-ink-secondary">{{ $event->company->name }} · {{ $event->starts_at->translatedFormat('j M Y, H\hi') }}</p>

        <ul class="mt-4 space-y-3 border-t border-separator pt-4">
            @foreach ($checks as $key => $check)
                <li wire:key="check-{{ $key }}" class="flex gap-2.5">
                    <span @class(['mt-px grid size-5 shrink-0 place-items-center rounded-full', 'bg-switch text-white' => $check['ok'], 'ring-[1.5px] ring-line-strong ring-inset' => ! $check['ok']])>
                        @if ($check['ok'])<x-ui.icon name="check" class="size-3" stroke-width="3" />@endif
                    </span>
                    <div class="min-w-0">
                        <p @class(['text-[14px]', 'font-medium' => ! $check['ok']])>{{ $check['label'] }}</p>
                        @foreach (array_slice($check['problems'], 0, 3) as $problem)
                            <p wire:key="problem-{{ $key }}-{{ $loop->index }}" class="text-[12px] text-ink-secondary">{{ $problem }}</p>
                        @endforeach
                    </div>
                </li>
            @endforeach
        </ul>

        @error('event') <x-ui.alert tone="danger" class="mt-4">{{ $message }}</x-ui.alert> @enderror

        <div class="mt-5 space-y-2">
            @if ($canPropose)
                <x-ui.textarea label="Message à la compagnie (facultatif)" name="proposalMessage" rows="2" wire:model="proposalMessage" />
                <x-ui.button type="button" class="w-full" wire:click="propose" wire:confirm="Proposer cet événement à {{ $event->company->name }} ? Les informations, points et grille seront verrouillés.">
                    {{ $event->status === \App\Enums\EventStatus::Negotiating ? 'Renvoyer la proposition' : 'Proposer à la compagnie' }}
                </x-ui.button>
            @endif
            @if ($event->status === \App\Enums\EventStatus::Accepted)
                <x-ui.button type="button" class="w-full" :disabled="! $canOpenSale" wire:click="openSale" wire:confirm="Ouvrir la vente des Pass ?">Ouvrir la vente</x-ui.button>
                @unless ($canOpenSale)
                    <p class="text-center text-[12px] text-ink-tertiary">Disponible quand toute la liste est cochée.</p>
                @endunless
            @endif
        </div>
    </x-ui.card>

    <x-ui.card padding="p-5">
        <p class="mb-4 text-headline">Échanges avec la compagnie</p>
        <x-ui.negotiation-thread :messages="$messages" viewer-side="acces_event" />
        @if ($canReply)
            <form wire:submit="sendReply" class="mt-4 space-y-2">
                <x-ui.textarea label="Répondre" name="reply" rows="2" wire:model="reply" />
                <x-ui.button size="sm" variant="secondary" class="w-full">Envoyer</x-ui.button>
            </form>
        @endif
    </x-ui.card>
</div>
