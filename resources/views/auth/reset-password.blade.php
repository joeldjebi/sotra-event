<x-layouts.auth title="Nouveau mot de passe">
    <h1 class="text-[28px] font-semibold tracking-tight">Nouveau mot de passe</h1>
    <p class="mt-1.5 text-[15px] text-ink-secondary">Au moins 12 caractères.</p>

    <form method="POST" action="{{ route('password.update') }}" class="mt-8 space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <x-ui.input label="Adresse e-mail" name="email" type="email" :value="$request->input('email')" autocomplete="username" required />
        <x-ui.input label="Nouveau mot de passe" name="password" type="password" autocomplete="new-password" required />
        <x-ui.input label="Confirmation" name="password_confirmation" type="password" autocomplete="new-password" required />
        <x-ui.button size="lg" class="w-full">Enregistrer</x-ui.button>
    </form>
</x-layouts.auth>
