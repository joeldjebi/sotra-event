<div>
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <x-ui.search wire:model.live.debounce.300ms="search" placeholder="Rechercher une compagnie" />
        <x-ui.button wire:click="create" type="button" icon="sparkles">Nouvelle compagnie</x-ui.button>
    </div>

    <x-ui.table>
        <x-slot:head>
            <th>Compagnie</th>
            <th>Commission</th>
            <th>Reversement</th>
            <th class="text-right">Utilisateurs</th>
            <th class="text-right">Bus</th>
            <th>Statut</th>
            <th></th>
        </x-slot:head>

        @forelse ($companies as $company)
            <tr wire:key="company-{{ $company->id }}" class="hover:bg-canvas/50">
                <td>
                    <p class="font-medium">{{ $company->name }}</p>
                    <p class="text-[13px] text-ink-secondary">{{ $company->legal_name ?? $company->email ?? '—' }}</p>
                </td>
                <td class="tabular-nums">{{ rtrim(rtrim($company->default_commission_rate, '0'), '.') }} %</td>
                <td class="text-ink-secondary">{{ $company->payout_method?->label() ?? 'Non renseigné' }}</td>
                <td class="text-right tabular-nums">{{ $company->users_count }}</td>
                <td class="text-right tabular-nums">{{ $company->buses_count }}</td>
                <td><x-ui.badge :tone="$company->is_active ? 'success' : 'neutral'">{{ $company->is_active ? 'Active' : 'Inactive' }}</x-ui.badge></td>
                <td class="text-right whitespace-nowrap">
                    <x-ui.button variant="ghost" size="sm" type="button" wire:click="toggleActive({{ $company->id }})"
                                 wire:confirm="{{ $company->is_active ? 'Désactiver cette compagnie ?' : 'Réactiver cette compagnie ?' }}">
                        {{ $company->is_active ? 'Désactiver' : 'Réactiver' }}
                    </x-ui.button>
                    <x-ui.button variant="secondary" size="sm" type="button" wire:click="edit({{ $company->id }})">Modifier</x-ui.button>
                </td>
            </tr>
        @empty
            <tr><td colspan="7"><x-ui.empty-state icon="building" title="Aucune compagnie" description="Ajoutez la première compagnie partenaire, par exemple la SOTRA." /></td></tr>
        @endforelse

        @if ($companies->hasPages())
            <x-slot:footer>{{ $companies->links() }}</x-slot:footer>
        @endif
    </x-ui.table>

    <x-ui.sheet model="showForm" :title="$editingId ? 'Modifier la compagnie' : 'Nouvelle compagnie'" subtitle="Fiche, contrat et compte de reversement">
        <form id="company-form" wire:submit="save" class="space-y-8">
            <section class="space-y-4">
                <x-ui.section-title title="Identité" />
                <x-ui.input label="Nom" name="form.name" wire:model="form.name" placeholder="SOTRA" />
                <x-ui.input label="Raison sociale" name="form.legal_name" wire:model="form.legal_name" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input label="E-mail" name="form.email" type="email" wire:model="form.email" />
                    <x-ui.input label="Téléphone" name="form.phone" wire:model="form.phone" />
                </div>
                <x-ui.input label="Adresse" name="form.address" wire:model="form.address" />
            </section>

            <section class="space-y-4">
                <x-ui.section-title title="Contrat" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input label="Référence du contrat" name="form.contract_reference" wire:model="form.contract_reference" />
                    <x-ui.input label="Début du contrat" name="form.contract_starts_on" type="date" wire:model="form.contract_starts_on" />
                </div>
                <x-ui.input label="Commission ACCES EVENT par défaut (%)" name="form.default_commission_rate" type="number" step="0.01" min="0" max="100"
                            wire:model="form.default_commission_rate" hint="Reprise à la création de chaque événement, modifiable par événement." />
            </section>

            <section class="space-y-4">
                <x-ui.section-title title="Compte de reversement" description="Chiffré en base. Les modifications sont tracées sans exposer les coordonnées." />
                <x-ui.select label="Mode de reversement" name="form.payout_method" wire:model.live="form.payout_method" :options="$payoutMethods" placeholder="Non renseigné" />
                @if ($form->payout_method === 'virement')
                    <x-ui.input label="Banque" name="form.bank_name" wire:model="form.bank_name" />
                    <x-ui.input label="Titulaire du compte" name="form.account_holder" wire:model="form.account_holder" />
                    <x-ui.input label="IBAN / RIB" name="form.iban" wire:model="form.iban" />
                @elseif ($form->payout_method === 'mobile_money')
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.input label="Opérateur" name="form.mobile_money_operator" wire:model="form.mobile_money_operator" placeholder="Orange Money" />
                        <x-ui.input label="Numéro marchand" name="form.mobile_money_number" wire:model="form.mobile_money_number" />
                    </div>
                @endif
            </section>

            <x-ui.switch label="Compagnie active" description="Une compagnie inactive ne reçoit plus d'événements." wire:model="form.is_active" />
        </form>

        <x-slot:footer>
            <x-ui.button variant="secondary" type="button" x-on:click="open = false">Annuler</x-ui.button>
            <x-ui.button form="company-form">Enregistrer</x-ui.button>
        </x-slot:footer>
    </x-ui.sheet>
</div>
