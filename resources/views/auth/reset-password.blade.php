<x-layouts.auth title="Nouveau mot de passe">
    <div class="text-center">
        <h1 class="text-title-1">Nouveau mot de passe</h1>
        <p class="mt-2 text-[15px] leading-snug text-ink-secondary">Au moins 12 caractères.</p>
    </div>

    <form method="POST" action="{{ route('password.update') }}" class="mt-8 space-y-3">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <x-ui.input floating label="Adresse e-mail" name="email" type="email" :value="$request->input('email')" autocomplete="username" required />
        <x-ui.input floating label="Nouveau mot de passe" name="password" type="password" autocomplete="new-password" required />
        <x-ui.input floating label="Confirmation" name="password_confirmation" type="password" autocomplete="new-password" required />
        <div class="pt-3"><x-ui.button size="lg" class="w-full">Enregistrer</x-ui.button></div>
    </form>
</x-layouts.auth>
