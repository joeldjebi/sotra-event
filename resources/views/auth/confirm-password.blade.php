<x-layouts.auth title="Confirmation">
    <h1 class="text-[28px] font-semibold tracking-tight">Confirmez votre identité</h1>
    <p class="mt-1.5 text-[15px] text-ink-secondary">Saisissez votre mot de passe pour continuer.</p>

    <form method="POST" action="{{ route('password.confirm.store') }}" class="mt-8 space-y-5">
        @csrf
        <x-ui.input label="Mot de passe" name="password" type="password" autocomplete="current-password" required autofocus />
        <x-ui.button size="lg" class="w-full">Confirmer</x-ui.button>
    </form>
</x-layouts.auth>
