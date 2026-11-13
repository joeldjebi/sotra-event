<x-layouts.auth title="Vérification">
    <div x-data="{ recovery: false }">
        <span class="grid size-12 place-items-center rounded-2xl bg-brand-soft text-brand"><x-ui.icon name="shield" class="size-6" /></span>
        <h1 class="mt-5 text-[28px] font-semibold tracking-tight">Vérification</h1>
        <p class="mt-1.5 text-[15px] text-ink-secondary" x-show="!recovery">Saisissez le code à 6 chiffres affiché par votre application d'authentification.</p>
        <p class="mt-1.5 text-[15px] text-ink-secondary" x-cloak x-show="recovery">Saisissez l'un de vos codes de secours.</p>

        <form method="POST" action="{{ route('two-factor.login.store') }}" class="mt-8 space-y-5">
            @csrf
            <div x-show="!recovery">
                <x-ui.input label="Code" name="code" inputmode="numeric" autocomplete="one-time-code" autofocus maxlength="6" class="text-center text-[22px] tracking-[0.5em]" x-bind:disabled="recovery" />
            </div>
            <div x-cloak x-show="recovery">
                <x-ui.input label="Code de secours" name="recovery_code" autocomplete="off" x-bind:disabled="!recovery" />
            </div>

            <x-ui.button size="lg" class="w-full">Valider</x-ui.button>
        </form>

        <button type="button" class="mt-5 w-full text-center text-[14px] font-medium text-brand hover:underline" @click="recovery = !recovery">
            <span x-show="!recovery">Utiliser un code de secours</span>
            <span x-cloak x-show="recovery">Utiliser l'application d'authentification</span>
        </button>
    </div>
</x-layouts.auth>
