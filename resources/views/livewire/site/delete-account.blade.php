<div class="mx-auto max-w-xl space-y-6 pt-2">
    <div>
        <h1 class="text-[30px] leading-tight font-semibold tracking-tight">Supprimer mon compte</h1>
        <p class="mt-1.5 text-[15px] text-ink-secondary">EVENT TRANS · application et site du Pass transport ACCES EVENT</p>
    </div>

    <x-ui.card padding="p-5 sm:p-6">
        <h2 class="text-[17px] font-semibold tracking-tight">Ce qui se passe quand vous supprimez votre compte</h2>
        <ul class="mt-3 space-y-2 text-[15px] text-ink-secondary">
            <li><span class="font-medium text-ink">Supprimé immédiatement</span> : votre numéro de téléphone, votre nom, vos connexions sur tous vos appareils, vos codes de connexion et l’historique des SMS et notifications qui vous ont été envoyés.</li>
            <li><span class="font-medium text-ink">Conservé sans lien avec vous</span> : les commandes, paiements et remboursements, que la loi nous oblige à garder pour la comptabilité (10 ans), ainsi que les avis et réclamations, rendus anonymes.</li>
            <li><span class="font-medium text-ink">Impossible si vous détenez un Pass pour un événement à venir</span> : annulez-le d’abord depuis « Mes Pass » (remboursement selon les <a href="{{ route('legal.terms') }}" class="font-medium text-brand-text hover:underline">conditions de vente</a>) ou attendez la fin de l’événement.</li>
            <li>La suppression est définitive. Vous pourrez recréer un compte plus tard avec le même numéro, sans retrouver vos anciens Pass.</li>
        </ul>
        <p class="mt-4 text-[14px] text-ink-secondary">Depuis l’application : <span class="font-medium text-ink">Profil › Supprimer mon compte</span>. Une question : <span class="font-medium text-ink">{{ config('eventtrans.legal.contact_email') }}</span>.</p>
    </x-ui.card>

    <x-ui.card padding="p-5 sm:p-6">
        @if ($step === 'phone')
            <h2 class="text-[17px] font-semibold tracking-tight">1. Votre numéro</h2>
            <p class="mt-1 text-[14px] text-ink-secondary">Pour vérifier que le compte est bien le vôtre, nous vous envoyons un code par SMS.</p>
            <form wire:submit="sendCode" class="mt-5 space-y-4">
                <x-ui.input label="Numéro de téléphone du compte" name="phone" type="tel" inputmode="tel" autocomplete="tel" wire:model="phone" placeholder="07 00 00 00 00" />
                <x-ui.button size="lg" class="w-full" wire:loading.attr="disabled">Recevoir le code</x-ui.button>
            </form>
        @elseif ($step === 'code')
            <h2 class="text-[17px] font-semibold tracking-tight">2. Le code reçu par SMS</h2>
            <p class="mt-1 text-[14px] text-ink-secondary">Envoyé au {{ $phone }}.</p>
            <form wire:submit="verify" class="mt-5 space-y-4">
                <x-ui.input label="Code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" wire:model="code" class="text-center text-[24px] tracking-[0.5em]" />
                <x-ui.button size="lg" class="w-full" wire:loading.attr="disabled">Vérifier</x-ui.button>
            </form>
            <button type="button" class="mt-4 text-[14px] font-medium text-brand hover:underline" wire:click="$set('step', 'phone')">Changer de numéro</button>
        @elseif ($step === 'confirm')
            <h2 class="text-[17px] font-semibold tracking-tight">3. Confirmer la suppression</h2>
            <form wire:submit="delete" class="mt-4 space-y-4">
                <label class="flex items-start gap-2.5 text-[15px]">
                    <input type="checkbox" wire:model="understood" class="mt-1 size-4 shrink-0 rounded accent-[var(--color-danger)]">
                    <span>Je comprends que mon compte {{ $phone }} et mes données personnelles seront supprimés définitivement.</span>
                </label>
                @error('understood')<p class="text-[14px] text-danger">{{ $message }}</p>@enderror
                <x-ui.button size="lg" variant="danger" class="w-full" wire:loading.attr="disabled">Supprimer définitivement mon compte</x-ui.button>
            </form>
        @else
            <div class="text-center">
                <span class="mx-auto grid size-12 place-items-center rounded-full bg-success-soft text-success"><x-ui.icon name="check" class="size-6" /></span>
                <h2 class="mt-3 text-[19px] font-semibold tracking-tight">Compte supprimé</h2>
                <p class="mt-1 text-[15px] text-ink-secondary">Il n’existe plus de compte EVENT TRANS associé à ce numéro. Vous êtes déconnecté de tous vos appareils.</p>
            </div>
        @endif
    </x-ui.card>
</div>
