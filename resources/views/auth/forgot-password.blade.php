<x-layouts.auth title="Mot de passe oublié">
    <div class="text-center">
        <h1 class="text-title-1">Mot de passe oublié</h1>
        <p class="mt-2 text-[15px] leading-snug text-ink-secondary">Nous vous envoyons un lien pour en choisir un nouveau.</p>
    </div>

    @if (session('status'))
        <x-ui.alert class="mt-6">{{ session('status') }}</x-ui.alert>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="mt-8 space-y-5">
        @csrf
        <x-ui.input floating label="Adresse e-mail" name="email" type="email" autocomplete="username" required autofocus />
        <x-ui.button size="lg" class="w-full">Envoyer le lien</x-ui.button>
    </form>

    <a href="{{ route('login') }}" class="mt-6 flex items-center justify-center gap-1 text-[14px] font-medium text-brand-text hover:underline"><x-ui.icon name="chevron-left" class="size-4" stroke-width="2.2" />Retour à la connexion</a>
</x-layouts.auth>
