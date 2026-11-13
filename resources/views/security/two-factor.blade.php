<x-layouts.auth title="Double authentification">
    <span class="grid size-12 place-items-center rounded-2xl bg-brand-soft text-brand"><x-ui.icon name="shield" class="size-6" /></span>

    @if ($step === 'start')
        <h1 class="mt-5 text-[28px] font-semibold tracking-tight">Protégez votre compte</h1>
        <p class="mt-1.5 text-[15px] text-ink-secondary">
            La double authentification est obligatoire pour accéder aux espaces EVENT TRANS.
            Installez une application comme Google Authenticator, Microsoft Authenticator ou l'app Mots de passe d'Apple.
        </p>
        <form method="POST" action="{{ route('two-factor.enable') }}" class="mt-8">
            @csrf
            <x-ui.button size="lg" class="w-full">Commencer</x-ui.button>
        </form>
    @elseif ($step === 'scan')
        <h1 class="mt-5 text-[28px] font-semibold tracking-tight">Scannez ce code</h1>
        <p class="mt-1.5 text-[15px] text-ink-secondary">Ouvrez votre application d'authentification et scannez le QR code, puis saisissez le code affiché.</p>

        <div class="mt-6 flex justify-center rounded-2xl bg-white p-4 ring-1 ring-hairline [&>svg]:size-44">{!! $qrCode !!}</div>
        <p class="mt-3 text-center text-[12px] text-ink-tertiary">
            Clé manuelle : <span class="font-mono tracking-wider text-ink-secondary">{{ trim(chunk_split((string) $secretKey, 4, ' ')) }}</span>
        </p>

        <form method="POST" action="{{ route('two-factor.confirm') }}" class="mt-6 space-y-5">
            @csrf
            <x-ui.input label="Code à 6 chiffres" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" autofocus required
                        :error="$errors->confirmTwoFactorAuthentication->first('code')"
                        class="text-center text-[22px] tracking-[0.5em]" />
            <x-ui.button size="lg" class="w-full">Activer</x-ui.button>
        </form>
    @else
        <h1 class="mt-5 text-[28px] font-semibold tracking-tight">{{ $justConfirmed ? 'C\'est activé' : 'Double authentification active' }}</h1>
        <p class="mt-1.5 text-[15px] text-ink-secondary">
            Conservez ces codes de secours en lieu sûr. Chacun permet une connexion si vous n'avez pas votre téléphone.
        </p>

        <ul class="mt-6 grid grid-cols-2 gap-2 rounded-2xl bg-canvas p-4 font-mono text-[13px] tracking-wide">
            @foreach ($recoveryCodes as $code)
                <li wire:key="code-{{ $loop->index }}">{{ $code }}</li>
            @endforeach
        </ul>

        <div class="mt-6 space-y-3">
            <x-ui.button :href="route('space')" size="lg" class="w-full">Continuer</x-ui.button>
            <form method="POST" action="{{ route('two-factor.regenerate-recovery-codes') }}">
                @csrf
                <x-ui.button variant="ghost" class="w-full">Générer de nouveaux codes</x-ui.button>
            </form>
        </div>
    @endif
</x-layouts.auth>
