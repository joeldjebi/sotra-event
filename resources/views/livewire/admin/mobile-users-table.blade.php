<div>
    <div class="mb-5 flex flex-wrap items-center gap-3">
        <x-ui.segmented label="Type d'utilisateur">
            <x-ui.segment :active="$tab === 'spectateurs'" wire:click="$set('tab', 'spectateurs')">Spectateurs <span class="font-normal text-ink-tertiary tabular-nums">{{ $customerCount }}</span></x-ui.segment>
            <x-ui.segment :active="$tab === 'personnel'" wire:click="$set('tab', 'personnel')">Convoyeurs & techniciens <span class="font-normal text-ink-tertiary tabular-nums">{{ $staffCount }}</span></x-ui.segment>
        </x-ui.segmented>
        <x-ui.search wire:model.live.debounce.300ms="search" placeholder="Nom ou téléphone" />
        @if ($tab === 'personnel')
            <div class="w-60"><x-ui.select label="" name="company" wire:model.live="company" :options="$companies" placeholder="Toutes les compagnies" /></div>
        @endif
    </div>

    @if ($tab === 'spectateurs')
        <x-ui.table>
            <x-slot:head><th>Spectateur</th><th>Téléphone</th><th>Connexion</th><th class="text-right">Commandes</th><th class="text-right">Pass</th><th>Inscrit</th><th>Dernière connexion</th></x-slot:head>
            @forelse ($customers as $customer)
                <tr wire:key="customer-{{ $customer->id }}">
                    <td class="font-medium">{{ $customer->fullName() ?: '—' }}</td>
                    <td class="font-mono text-[13px]">{{ $customer->phone }}</td>
                    <td>
                        @if ($customer->password !== null)
                            <x-ui.badge tone="success">Mot de passe</x-ui.badge>
                        @else
                            <x-ui.badge>Code SMS seul</x-ui.badge>
                        @endif
                    </td>
                    <td class="text-right tabular-nums">{{ $customer->orders_count }}</td>
                    <td class="text-right tabular-nums">{{ $customer->passes_count }}</td>
                    <td class="text-ink-secondary">{{ $customer->created_at?->format('d/m/Y') }}</td>
                    <td class="text-ink-secondary">{{ $customer->last_login_at?->diffForHumans() ?? 'Jamais' }}</td>
                </tr>
            @empty
                <tr><td colspan="7"><x-ui.empty-state icon="users" title="Aucun spectateur" description="Les spectateurs apparaissent ici dès leur inscription dans l'application ou sur le site." /></td></tr>
            @endforelse
            @if ($customers->hasPages())
                <x-slot:footer>{{ $customers->links() }}</x-slot:footer>
            @endif
        </x-ui.table>
        <p class="mt-3 text-[13px] text-ink-secondary">« Code SMS seul » : spectateur inscrit avant les mots de passe ou sur le site web. Il choisit son mot de passe dans l'application avec « Mot de passe oublié ».</p>
    @else
        <x-ui.table>
            <x-slot:head><th>Nom</th><th>Fonction</th><th>Compagnie</th><th>Téléphone</th><th>Accès mobile</th><th>Dernière connexion</th></x-slot:head>
            @forelse ($staff as $member)
                <tr wire:key="staff-{{ $member->id }}" @class(['opacity-55' => ! $member->is_active])>
                    <td class="font-medium">{{ $member->fullName() }}</td>
                    <td><x-ui.badge>{{ $member->role->label() }}</x-ui.badge></td>
                    <td>{{ $member->company->name }}</td>
                    <td class="font-mono text-[13px]">{{ $member->phone }}</td>
                    <td>
                        @if (! $member->is_active)
                            <x-ui.badge>Désactivé</x-ui.badge>
                        @elseif ($member->password === null)
                            <x-ui.badge tone="warning">Sans mot de passe</x-ui.badge>
                        @elseif ($member->must_change_password)
                            <x-ui.badge>Mot de passe à changer</x-ui.badge>
                        @else
                            <x-ui.badge tone="success">Actif</x-ui.badge>
                        @endif
                    </td>
                    <td class="text-ink-secondary">{{ $member->last_login_at?->diffForHumans() ?? 'Jamais' }}</td>
                </tr>
            @empty
                <tr><td colspan="6"><x-ui.empty-state icon="id-card" title="Aucun convoyeur ni technicien" description="Chaque compagnie enregistre son personnel dans son espace (Chauffeurs & convoyeurs)." /></td></tr>
            @endforelse
            @if ($staff->hasPages())
                <x-slot:footer>{{ $staff->links() }}</x-slot:footer>
            @endif
        </x-ui.table>
        <p class="mt-3 text-[13px] text-ink-secondary">Les mots de passe du personnel sont donnés par l'exploitation de chaque compagnie (Chauffeurs & convoyeurs).</p>
    @endif
</div>
