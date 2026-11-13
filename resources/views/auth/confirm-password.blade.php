<x-layouts.auth title="Confirmation">
    <div class="text-center">
        <span class="mx-auto grid size-14 place-items-center rounded-full bg-brand-soft text-brand-text"><x-ui.icon name="lock" class="size-7" /></span>
        <h1 class="mt-5 text-title-1">Confirmez votre identité</h1>
        <p class="mt-2 text-[15px] leading-snug text-ink-secondary">Saisissez votre mot de passe pour continuer.</p>
    </div>

    <form method="POST" action="{{ route('password.confirm.store') }}" class="mt-8 space-y-5">
        @csrf
        <x-ui.input floating label="Mot de passe" name="password" type="password" autocomplete="current-password" required autofocus />
        <x-ui.button size="lg" class="w-full">Confirmer</x-ui.button>
    </form>
</x-layouts.auth>
