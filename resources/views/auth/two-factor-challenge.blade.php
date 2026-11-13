<x-layouts.auth title="Vérification">
    <div x-data="{ recovery: false }">
        <div class="text-center">
            <span class="mx-auto grid size-14 place-items-center rounded-full bg-brand-soft text-brand-text"><x-ui.icon name="shield" class="size-7" /></span>
            <h1 class="mt-5 text-title-1">Vérification</h1>
            <p class="mt-2 text-[15px] leading-snug text-ink-secondary" x-show="!recovery">Saisissez le code à 6 chiffres affiché par votre application d'authentification.</p>
            <p class="mt-2 text-[15px] leading-snug text-ink-secondary" x-cloak x-show="recovery">Saisissez l'un de vos codes de secours.</p>
        </div>

        <form method="POST" action="{{ route('two-factor.login.store') }}" class="mt-8 space-y-5">
            @csrf
            <div x-show="!recovery">
                <x-ui.input label="Code" name="code" inputmode="numeric" autocomplete="one-time-code" autofocus maxlength="6" class="h-16 text-center font-mono !text-[28px] font-medium tracking-[0.5em]" x-bind:disabled="recovery" />
            </div>
            <div x-cloak x-show="recovery">
                <x-ui.input floating label="Code de secours" name="recovery_code" autocomplete="off" x-bind:disabled="!recovery" />
            </div>

            <x-ui.button size="lg" class="w-full">Valider</x-ui.button>
        </form>

        <button type="button" class="mt-5 w-full text-center text-[14px] font-medium text-brand-text hover:underline" @click="recovery = !recovery">
            <span x-show="!recovery">Utiliser un code de secours</span>
            <span x-cloak x-show="recovery">Utiliser l'application d'authentification</span>
        </button>
    </div>
</x-layouts.auth>
