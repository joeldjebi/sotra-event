<div class="mx-auto max-w-sm pt-6">
    <span class="grid size-14 place-items-center rounded-2xl bg-brand-soft text-brand"><x-ui.icon name="phone" class="size-7" /></span>
    <h1 class="mt-5 text-[30px] leading-tight font-semibold tracking-tight">{{ $codeSent ? 'Saisissez le code' : 'Connexion' }}</h1>
    <p class="mt-1.5 text-[15px] text-ink-secondary">
        {{ $codeSent ? 'Nous avons envoyé un code à 6 chiffres au '.$phone.'.' : 'Votre numéro suffit : vous recevez un code par SMS. Pas de mot de passe.' }}
    </p>

    @if (! $codeSent)
        <form wire:submit="sendCode" class="mt-8 space-y-5">
            <x-ui.input label="Numéro de téléphone" name="phone" type="tel" inputmode="tel" autocomplete="tel" wire:model="phone" placeholder="07 00 00 00 00" autofocus />
            <x-ui.button size="lg" class="w-full" wire:loading.attr="disabled">Recevoir le code</x-ui.button>
        </form>
    @else
        <form wire:submit="verify" class="mt-8 space-y-5">
            <x-ui.input label="Code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" wire:model="code" autofocus class="text-center text-[24px] tracking-[0.5em]" />
            <x-ui.button size="lg" class="w-full" wire:loading.attr="disabled">Se connecter</x-ui.button>
        </form>
        <div class="mt-4 flex justify-between text-[14px]">
            <button type="button" class="font-medium text-brand hover:underline" wire:click="$set('codeSent', false)">Changer de numéro</button>
            <button type="button" class="font-medium text-brand hover:underline" wire:click="sendCode">Renvoyer le code</button>
        </div>
        @if (app()->isLocal())
            <p class="mt-6 text-center text-[13px] text-ink-tertiary">Démo : le code s'affiche sur <a href="{{ route('dev.sms') }}" target="_blank" class="underline">/dev/sms</a>.</p>
        @endif
    @endif
</div>
