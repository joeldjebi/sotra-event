<div>
    <div class="text-center">
        <span class="mx-auto grid size-14 place-items-center rounded-full bg-brand-soft text-brand-text"><x-ui.icon name="key" class="size-7" /></span>
        <h1 class="mt-5 text-title-1">{{ $forced ? 'Choisissez votre mot de passe' : 'Changer de mot de passe' }}</h1>
        <p class="mt-2 text-[15px] leading-snug text-ink-secondary">
            {{ $forced
                ? 'Votre compte a été ouvert avec un mot de passe provisoire. Remplacez-le par un mot de passe personnel pour accéder à votre espace.'
                : 'Au moins 12 caractères, avec des lettres et des chiffres.' }}
        </p>
    </div>

    <form wire:submit="save" class="mt-8 space-y-4">
        <x-ui.input label="{{ $forced ? 'Mot de passe provisoire' : 'Mot de passe actuel' }}" name="current_password" type="password" autocomplete="current-password" wire:model="current_password" />
        <x-ui.input label="Nouveau mot de passe" name="password" type="password" autocomplete="new-password" wire:model="password" hint="Au moins 12 caractères, avec des lettres et des chiffres." />
        <x-ui.input label="Confirmer le nouveau mot de passe" name="password_confirmation" type="password" autocomplete="new-password" wire:model="password_confirmation" />
        <x-ui.button size="lg" class="w-full" wire:loading.attr="disabled">Enregistrer le mot de passe</x-ui.button>
    </form>

    @unless ($forced)
        <p class="mt-6 text-center text-[14px]"><a href="{{ route('space') }}" class="font-medium text-brand-text hover:underline">Retour à mon espace</a></p>
    @endunless
</div>
