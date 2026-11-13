<x-layouts.auth title="Connexion">
    <h1 class="text-[28px] font-semibold tracking-tight">Connexion</h1>
    <p class="mt-1.5 text-[15px] text-ink-secondary">Espace professionnel ACCES EVENT, compagnies et promoteurs.</p>

    @if (session('status'))
        <x-ui.alert class="mt-6">{{ session('status') }}</x-ui.alert>
    @endif

    <form method="POST" action="{{ route('login.store') }}" class="mt-8 space-y-5">
        @csrf
        <x-ui.input label="Adresse e-mail" name="email" type="email" autocomplete="username" required autofocus placeholder="prenom.nom@exemple.ci" />
        <x-ui.input label="Mot de passe" name="password" type="password" autocomplete="current-password" required />

        <div class="flex items-center justify-between">
            <label class="flex items-center gap-2 text-[14px] text-ink-secondary">
                <input type="checkbox" name="remember" class="size-4 rounded border-hairline text-brand focus:ring-brand/30">
                Rester connecté
            </label>
            <a href="{{ route('password.request') }}" class="text-[14px] font-medium text-brand hover:underline">Mot de passe oublié ?</a>
        </div>

        <x-ui.button size="lg" class="w-full">Se connecter</x-ui.button>
    </form>
</x-layouts.auth>
