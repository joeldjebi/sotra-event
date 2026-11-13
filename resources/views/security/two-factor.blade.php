<x-layouts.auth title="Double authentification">
    <div class="text-center">
        <span class="mx-auto grid size-14 place-items-center rounded-full bg-brand-soft text-brand-text"><x-ui.icon name="shield" class="size-7" /></span>
    </div>

    @if ($step === 'start')
        <div class="text-center">
            <h1 class="mt-5 text-title-1">Protégez votre compte</h1>
            <p class="mt-2 text-[15px] leading-snug text-ink-secondary">
                La double authentification est obligatoire pour accéder aux espaces EVENT TRANS.
                Installez une application comme Google Authenticator, Microsoft Authenticator ou l'app Mots de passe d'Apple.
            </p>
        </div>
        <form method="POST" action="{{ route('two-factor.enable') }}" class="mt-8">
            @csrf
            <x-ui.button size="lg" class="w-full">Commencer</x-ui.button>
        </form>
    @elseif ($step === 'scan')
        <div class="text-center">
            <h1 class="mt-5 text-title-1">Scannez ce code</h1>
            <p class="mt-2 text-[15px] leading-snug text-ink-secondary">Ouvrez votre application d'authentification et scannez le QR code, puis saisissez le code affiché.</p>
        </div>

        {{-- Le QR code reste sur fond blanc, y compris en mode sombre, pour rester lisible par les appareils photo. --}}
        <div class="mx-auto mt-6 flex w-fit justify-center rounded-[var(--radius-card)] bg-white p-4 shadow-[var(--shadow-card)] ring-1 ring-hairline [&>svg]:size-44">{!! $qrCode !!}</div>
        <p class="mt-3 text-center text-footnote text-ink-tertiary">
            Clé manuelle : <span class="font-mono tracking-wider text-ink-secondary select-all">{{ trim(chunk_split((string) $secretKey, 4, ' ')) }}</span>
        </p>

        <form method="POST" action="{{ route('two-factor.confirm') }}" class="mt-6 space-y-5">
            @csrf
            <x-ui.input label="Code à 6 chiffres" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" autofocus required
                        :error="$errors->confirmTwoFactorAuthentication->first('code')"
                        class="h-16 text-center font-mono !text-[28px] font-medium tracking-[0.5em]" />
            <x-ui.button size="lg" class="w-full">Activer</x-ui.button>
        </form>
    @else
        <div class="text-center">
            <h1 class="mt-5 text-title-1">{{ $justConfirmed ? 'C\'est activé' : 'Double authentification active' }}</h1>
            <p class="mt-2 text-[15px] leading-snug text-ink-secondary">
                Conservez ces codes de secours en lieu sûr. Chacun permet une connexion si vous n'avez pas votre téléphone.
            </p>
        </div>

        <ul class="mt-6 grid grid-cols-2 gap-x-4 gap-y-2 rounded-[var(--radius-card)] bg-fill-subtle p-5 text-center font-mono text-[14px] tracking-wide ring-1 ring-hairline select-all">
            @foreach ($recoveryCodes as $code)
                <li wire:key="code-{{ $loop->index }}">{{ $code }}</li>
            @endforeach
        </ul>

        <div class="mt-6 space-y-2">
            <x-ui.button :href="route('space')" size="lg" class="w-full">Continuer</x-ui.button>
            <form method="POST" action="{{ route('two-factor.regenerate-recovery-codes') }}">
                @csrf
                <x-ui.button variant="plain" class="w-full">Générer de nouveaux codes</x-ui.button>
            </form>
        </div>
    @endif
</x-layouts.auth>
