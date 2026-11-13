<x-layouts.auth title="Connexion">
    <div class="text-center">
        <h1 class="text-title-1">Connexion</h1>
        <p class="mt-2 text-[15px] leading-snug text-ink-secondary">Espace professionnel ACCES EVENT, compagnies et promoteurs.</p>
    </div>

    @if (session('status'))
        <x-ui.alert class="mt-6">{{ session('status') }}</x-ui.alert>
    @endif

    <form method="POST" action="{{ route('login.store') }}" class="mt-8 space-y-3">
        @csrf
        <x-ui.input floating label="Adresse e-mail" name="email" type="email" autocomplete="username" required autofocus placeholder="prenom.nom@exemple.ci" />
        <x-ui.input floating label="Mot de passe" name="password" type="password" autocomplete="current-password" required />

        <div class="flex items-center justify-between gap-3 px-1 pt-2 pb-3">
            <label class="flex cursor-pointer items-center gap-2.5 text-[14px] text-ink">
                <input type="checkbox" name="remember" class="ui-check">
                Rester connecté
            </label>
            <a href="{{ route('password.request') }}" class="text-[14px] font-medium text-brand-text hover:underline">Mot de passe oublié ?</a>
        </div>

        <x-ui.button size="lg" class="w-full">Se connecter</x-ui.button>
    </form>
</x-layouts.auth>
