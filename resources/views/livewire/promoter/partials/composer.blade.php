{{-- Bloc « Message aux spectateurs » (PR-04), alimenté par le trait ComposesPromoterMessages. --}}
<x-ui.card>
    <div class="flex items-baseline justify-between gap-3">
        <h2 class="text-[17px] font-semibold tracking-tight">Message aux spectateurs</h2>
        <span class="text-[12px] text-ink-tertiary">{{ $composer['remaining'] }} envoi(s) restant(s)</span>
    </div>
    @can(\App\Enums\Permission::PromoterMessagesSend->value)
        <form wire:submit="sendMessage" class="mt-4 space-y-4">
            <div class="grid gap-3 sm:grid-cols-2">
                <x-ui.select label="Destinataires" name="audience" wire:model.live="audience" :options="$composer['audiences']" />
                @if ($composer['targets'] !== [])
                    <x-ui.select :label="$audience === 'offre' ? 'Offre' : ($audience === 'bus' ? 'Bus' : 'Point')" name="audienceValue" wire:model.live="audienceValue" :options="$composer['targets']" placeholder="Choisir" />
                @elseif ($audience !== 'tous')
                    <p class="self-end pb-2 text-[13px] text-ink-tertiary">Aucune cible disponible pour l'instant.</p>
                @endif
            </div>
            <x-ui.textarea label="Message" name="body" wire:model="body" rows="3" :hint="'Préfixé du nom de l\'événement · '.$composer['maxLength'].' caractères au plus'"
                           placeholder="Ex. : ouverture des portes retardée à 18 h 30. Les bus vous déposent au parking P3." />
            <fieldset>
                <legend class="text-[14px] font-medium">Canaux</legend>
                <div class="mt-2 flex flex-wrap gap-4">
                    @foreach (['push' => 'Notification', 'sms' => 'SMS', 'whatsapp' => 'WhatsApp'] as $value => $label)
                        <label wire:key="channel-{{ $value }}" class="flex items-center gap-2 text-[14px]">
                            <input type="checkbox" value="{{ $value }}" wire:model="channels" class="size-4 rounded accent-[var(--color-brand)]"> {{ $label }}
                        </label>
                    @endforeach
                </div>
                @error('channels')<p class="mt-1 text-[13px] text-danger">{{ $message }}</p>@enderror
            </fieldset>
            <div class="flex items-center justify-between gap-3">
                <p class="text-[13px] text-ink-secondary">{{ number_format($composer['recipients'], 0, ',', ' ') }} spectateur(s) recevront ce message.</p>
                <x-ui.button :disabled="$composer['remaining'] === 0" wire:confirm="Envoyer ce message aux spectateurs ?">Envoyer</x-ui.button>
            </div>
        </form>
    @else
        <p class="mt-3 text-[14px] text-ink-tertiary">Vous n'avez pas le droit d'envoyer des messages.</p>
    @endcan
</x-ui.card>
