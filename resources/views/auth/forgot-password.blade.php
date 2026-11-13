<x-layouts.auth title="Mot de passe oublié">
    <h1 class="text-[28px] font-semibold tracking-tight">Mot de passe oublié</h1>
    <p class="mt-1.5 text-[15px] text-ink-secondary">Nous vous envoyons un lien pour en choisir un nouveau.</p>

    @if (session('status'))
        <x-ui.alert class="mt-6">{{ session('status') }}</x-ui.alert>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="mt-8 space-y-5">
        @csrf
        <x-ui.input label="Adresse e-mail" name="email" type="email" autocomplete="username" required autofocus />
        <x-ui.button size="lg" class="w-full">Envoyer le lien</x-ui.button>
    </form>

    <a href="{{ route('login') }}" class="mt-6 block text-center text-[14px] font-medium text-brand hover:underline">Retour à la connexion</a>
</x-layouts.auth>
