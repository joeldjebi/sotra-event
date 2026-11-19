<div class="mx-auto max-w-2xl space-y-6 pt-2">
    <div>
        <span class="grid size-14 place-items-center rounded-2xl bg-brand-soft text-brand"><x-ui.icon name="megaphone" class="size-7" /></span>
        <h1 class="mt-5 text-[30px] leading-tight font-semibold tracking-tight">Devenir promoteur</h1>
        <p class="mt-1.5 text-[15px] text-ink-secondary">
            Organisateur d'événements ? Ouvrez votre espace ACCES TRANS : proposez vos événements, suivez l'arrivée de votre public
            et envoyez-lui des messages. Votre demande est validée par ACCES EVENT.
        </p>
    </div>

    @if ($step === 'form')
        <form wire:submit="sendCode" class="space-y-6">
            <x-ui.card padding="p-5 sm:p-6">
                <h2 class="text-[17px] font-semibold tracking-tight">1. Votre structure</h2>
                <div class="mt-5 space-y-4">
                    <x-ui.input label="Nom de la structure" name="name" wire:model="name" placeholder="Ex. Gbairai Productions" />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.input label="Téléphone" name="phone" type="tel" inputmode="tel" autocomplete="tel" wire:model="phone" placeholder="07 00 00 00 00"
                                    hint="Vous recevrez un code par SMS pour le vérifier." />
                        <x-ui.input label="E-mail de la structure (facultatif)" name="email" type="email" wire:model="email" />
                    </div>
                    <x-ui.input label="Adresse (facultatif)" name="address" wire:model="address" placeholder="Commune, quartier" />
                </div>
            </x-ui.card>

            <x-ui.card padding="p-5 sm:p-6">
                <h2 class="text-[17px] font-semibold tracking-tight">2. Justificatifs <span class="font-normal text-ink-secondary">(facultatifs)</span></h2>
                <p class="mt-1 text-[14px] text-ink-secondary">
                    Vous pouvez les envoyer maintenant ou plus tard depuis votre espace. Sans eux, votre dossier est marqué incomplet
                    et sa validation peut prendre plus de temps. PDF, JPG ou PNG, 5 Mo au plus.
                </p>
                <div class="mt-5 space-y-4">
                    <x-ui.input label="Numéro RCCM" name="rccm_number" wire:model="rccm_number" placeholder="CI-ABJ-2024-B-12345" />
                    @foreach (['rccm_document' => 'Extrait RCCM', 'identity_document' => 'Pièce d\'identité du responsable'] as $field => $label)
                        <div wire:key="upload-{{ $field }}">
                            <label for="{{ $field }}" class="block px-0.5 text-footnote font-medium text-ink-secondary">{{ $label }}</label>
                            <input id="{{ $field }}" type="file" wire:model="{{ $field }}" accept=".pdf,image/jpeg,image/png" class="mt-1.5 block w-full text-[14px]">
                            <p wire:loading wire:target="{{ $field }}" class="mt-1 text-footnote text-ink-tertiary">Envoi du fichier…</p>
                            @error($field)<p class="mt-1 text-footnote text-danger">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                </div>
            </x-ui.card>

            <x-ui.card padding="p-5 sm:p-6">
                <h2 class="text-[17px] font-semibold tracking-tight">3. Votre compte</h2>
                <p class="mt-1 text-[14px] text-ink-secondary">Vous serez l'administrateur de l'espace promoteur : vous créerez ensuite les comptes de votre équipe.</p>
                <div class="mt-5 space-y-4">
                    <x-ui.input label="Nom du responsable" name="account_name" wire:model="account_name" placeholder="Prénom Nom" autocomplete="name" />
                    <x-ui.input label="E-mail de connexion" name="account_email" type="email" wire:model="account_email" autocomplete="email" />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.input label="Mot de passe" name="password" type="password" wire:model="password" autocomplete="new-password"
                                    hint="Au moins 12 caractères, avec des lettres et des chiffres." />
                        <x-ui.input label="Confirmer le mot de passe" name="password_confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password" />
                    </div>
                    <div>
                        <label class="flex items-start gap-2.5 text-[15px]">
                            <input type="checkbox" wire:model="accept_terms" class="ui-check mt-1 shrink-0">
                            <span>J'accepte les <a href="{{ route('legal.terms') }}" target="_blank" class="font-medium text-brand-text hover:underline">conditions générales</a>
                                et la <a href="{{ route('legal.privacy') }}" target="_blank" class="font-medium text-brand-text hover:underline">politique de confidentialité</a>.</span>
                        </label>
                        @error('accept_terms')<p class="mt-1 text-footnote text-danger">{{ $message }}</p>@enderror
                    </div>
                </div>
            </x-ui.card>

            <x-ui.button size="lg" class="w-full" wire:loading.attr="disabled">Vérifier mon téléphone</x-ui.button>
            <p class="text-center text-[14px] text-ink-secondary">Déjà un compte ? <a href="{{ route('login') }}" class="font-medium text-brand-text hover:underline">Se connecter</a></p>
        </form>
    @elseif ($step === 'code')
        <x-ui.card padding="p-5 sm:p-6">
            <h2 class="text-[17px] font-semibold tracking-tight">Le code reçu par SMS</h2>
            <p class="mt-1 text-[14px] text-ink-secondary">Envoyé au {{ $phone }}. Il confirme que ce numéro est bien le vôtre.</p>
            <form wire:submit="verify" class="mt-5 space-y-4">
                <x-ui.input label="Code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" wire:model="code" autofocus class="text-center text-[24px] tracking-[0.5em]" />
                @foreach (['name', 'phone', 'account_email', 'accept_terms'] as $field)
                    @error($field)<p wire:key="code-error-{{ $field }}" class="text-[14px] text-danger">{{ $message }}</p>@enderror
                @endforeach
                <x-ui.button size="lg" class="w-full" wire:loading.attr="disabled">Envoyer ma demande</x-ui.button>
            </form>
            <div class="mt-4 flex justify-between text-[14px]">
                <button type="button" class="font-medium text-brand hover:underline" wire:click="back">Modifier mes informations</button>
                <button type="button" class="font-medium text-brand hover:underline" wire:click="sendCode">Renvoyer le code</button>
            </div>
            @if (app()->isLocal())
                <p class="mt-6 text-center text-[13px] text-ink-tertiary">Démo : le code s'affiche sur <a href="{{ route('dev.sms') }}" target="_blank" class="underline">/dev/sms</a>.</p>
            @endif
        </x-ui.card>
    @else
        <x-ui.card padding="p-6 sm:p-8">
            <div class="text-center">
                <span class="mx-auto grid size-12 place-items-center rounded-full bg-success-soft text-success"><x-ui.icon name="check" class="size-6" /></span>
                <h2 class="mt-3 text-[19px] font-semibold tracking-tight">Demande envoyée</h2>
                <p class="mt-1 text-[15px] text-ink-secondary">
                    ACCES EVENT examine votre demande. Vous recevrez un SMS dès qu'elle sera validée.
                    En attendant, vous pouvez vous connecter pour suivre son avancement et compléter votre dossier.
                </p>
                <x-ui.button :href="route('login')" class="mt-5">Se connecter</x-ui.button>
            </div>
        </x-ui.card>
    @endif
</div>
