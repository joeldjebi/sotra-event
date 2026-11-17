{{-- Identifiants donnés par l'administrateur, affichés une seule fois (trait IssuesInitialPasswords). --}}
@if ($issuedCredentials)
    <div class="mb-5 rounded-[var(--radius-card)] bg-surface p-4 shadow-[var(--shadow-card)] ring-1 ring-hairline sm:p-5" role="status">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-[15px] font-semibold">Identifiants de {{ $issuedCredentials['name'] }}</p>
                <p class="text-[13px] text-ink-secondary">À transmettre de façon sûre (en main propre, par téléphone). Ils ne seront plus affichés. Le mot de passe devra être changé à la première connexion.</p>
            </div>
            <x-ui.button variant="secondary" size="sm" type="button" wire:click="dismissCredentials">J’ai transmis les identifiants</x-ui.button>
        </div>
        <dl class="mt-3 grid gap-2 text-[14px] sm:grid-cols-3">
            <div><dt class="text-ink-tertiary">Adresse de connexion</dt><dd class="font-medium">{{ route('login') }}</dd></div>
            <div><dt class="text-ink-tertiary">E-mail</dt><dd class="font-medium break-all">{{ $issuedCredentials['email'] }}</dd></div>
            <div><dt class="text-ink-tertiary">Mot de passe initial</dt><dd><code class="rounded-md bg-fill px-2 py-0.5 font-mono text-[15px] font-semibold select-all">{{ $issuedCredentials['password'] }}</code></dd></div>
        </dl>
    </div>
@endif
