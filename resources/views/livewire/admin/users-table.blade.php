<div>
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-3">
            <x-ui.search wire:model.live.debounce.300ms="search" placeholder="Nom ou e-mail" />
            {{-- Contrôle segmenté façon iOS --}}
            <x-ui.segmented label="Filtrer par espace">
                <x-ui.segment :active="$spaceFilter === ''" wire:click="$set('spaceFilter', '')">Tous</x-ui.segment>
                @foreach (['admin' => 'ACCES EVENT', 'company' => 'Compagnies', 'promoter' => 'Promoteurs'] as $value => $label)
                    <x-ui.segment wire:key="filter-{{ $value }}" :active="$spaceFilter === $value" wire:click="$set('spaceFilter', '{{ $value }}')">{{ $label }}</x-ui.segment>
                @endforeach
            </x-ui.segmented>
        </div>
        <x-ui.button wire:click="create" type="button" icon="sparkles">Nouvel utilisateur</x-ui.button>
    </div>

    <x-ui.table>
        <x-slot:head>
            <th>Utilisateur</th>
            <th>Rôles</th>
            <th>Organisation</th>
            <th>Sécurité</th>
            <th>Dernière connexion</th>
            <th></th>
        </x-slot:head>

        @forelse ($users as $user)
            <tr wire:key="user-{{ $user->id }}" @class(['hover:bg-fill-subtle', 'opacity-55' => ! $user->is_active])>
                <td>
                    <div class="flex items-center gap-3">
                        <x-ui.avatar :initials="$user->initials()" />
                        <div>
                            <p class="font-medium">{{ $user->name }} @unless ($user->is_active)<x-ui.badge class="ml-1">Désactivé</x-ui.badge>@endunless</p>
                            <p class="text-[13px] text-ink-secondary">{{ $user->email }}</p>
                        </div>
                    </div>
                </td>
                <td>
                    @foreach ($user->businessRoles() as $role)
                        <span wire:key="role-{{ $user->id }}-{{ $role->value }}" class="mr-1 inline-flex"><x-ui.badge tone="brand">{{ $role->label() }}</x-ui.badge></span>
                    @endforeach
                </td>
                <td class="text-ink-secondary">{{ $user->company?->name ?? $user->promoter?->name ?? 'ACCES EVENT' }}</td>
                <td>
                    @if ($user->hasEnabledTwoFactorAuthentication())
                        <x-ui.badge tone="success">2FA active</x-ui.badge>
                    @else
                        <x-ui.badge tone="warning">2FA à activer</x-ui.badge>
                    @endif
                </td>
                <td class="text-[13px] text-ink-secondary">{{ $user->last_login_at?->diffForHumans() ?? 'Jamais' }}</td>
                <td class="text-right whitespace-nowrap" x-data="{ menu: false }">
                    <div class="relative inline-block text-left">
                        <x-ui.button variant="secondary" size="sm" type="button" wire:click="edit({{ $user->id }})">Modifier</x-ui.button>
                        <button type="button" class="ml-1 inline-grid size-8 place-items-center rounded-full bg-fill text-ink-secondary transition hover:bg-fill-strong hover:text-ink active:scale-90" x-on:click="menu = !menu" aria-label="Plus d'actions" :aria-expanded="menu">
                            <svg class="size-4" viewBox="0 0 24 24" fill="currentColor"><circle cx="5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="19" cy="12" r="1.6"/></svg>
                        </button>
                        <div x-cloak x-show="menu" x-on:click.outside="menu = false" x-transition.origin.top.right
                             class="ui-popover elevated absolute right-0 z-20 mt-2 w-64 p-1.5 text-left" role="menu">
                            <button type="button" role="menuitem" class="flex h-9 w-full items-center rounded-[8px] px-2.5 text-left text-[14px] transition-colors hover:bg-brand hover:text-white" wire:click="sendInvitation({{ $user->id }})" x-on:click="menu = false">Renvoyer le lien de connexion</button>
                            <button type="button" role="menuitem" class="flex h-9 w-full items-center rounded-[8px] px-2.5 text-left text-[14px] transition-colors hover:bg-brand hover:text-white" wire:click="resetTwoFactor({{ $user->id }})"
                                    wire:confirm="Réinitialiser la double authentification ? L'utilisateur devra la réactiver à sa prochaine connexion." x-on:click="menu = false">Réinitialiser la 2FA</button>
                            @if ($user->id !== auth()->id())
                                <button type="button" role="menuitem" @class(['flex h-9 w-full items-center rounded-[8px] px-2.5 text-left text-[14px] transition-colors', 'text-danger hover:bg-danger hover:text-white' => $user->is_active, 'hover:bg-brand hover:text-white' => ! $user->is_active])
                                        wire:click="toggleActive({{ $user->id }})" wire:confirm="{{ $user->is_active ? 'Désactiver ce compte ?' : 'Réactiver ce compte ?' }}" x-on:click="menu = false">
                                    {{ $user->is_active ? 'Désactiver le compte' : 'Réactiver le compte' }}
                                </button>
                            @endif
                        </div>
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="6"><x-ui.empty-state icon="users" title="Aucun utilisateur" /></td></tr>
        @endforelse

        @if ($users->hasPages())
            <x-slot:footer>{{ $users->links() }}</x-slot:footer>
        @endif
    </x-ui.table>

    <x-ui.sheet model="showForm" :title="$editingId ? 'Modifier l\'utilisateur' : 'Nouvel utilisateur'"
                :subtitle="$editingId ? null : 'Un lien lui sera envoyé pour choisir son mot de passe.'">
        <form id="user-form" wire:submit="save" class="space-y-6">
            <div class="space-y-4">
                <x-ui.input label="Nom complet" name="name" wire:model="name" />
                <x-ui.input label="E-mail" name="email" type="email" wire:model="email" />
                <x-ui.input label="Téléphone" name="phone" wire:model="phone" />
            </div>

            <div class="space-y-4">
                <x-ui.select label="Espace" name="space" wire:model.live="space" :options="$spaces" />

                @if ($space === 'company')
                    <x-ui.select label="Compagnie" name="company_id" wire:model="company_id" :options="$companies" placeholder="Choisir une compagnie" />
                @elseif ($space === 'promoter')
                    <x-ui.select label="Promoteur" name="promoter_id" wire:model="promoter_id" :options="$promoters" placeholder="Choisir un promoteur" />
                @endif

                <div>
                    <p class="mb-1.5 px-0.5 text-footnote font-medium text-ink-secondary">Rôles</p>
                    <div class="divide-y divide-separator rounded-[var(--radius-card)] bg-surface px-3 py-1 [&>label]:rounded-none shadow-[var(--shadow-card)] ring-1 ring-hairline">
                        @foreach ($availableRoles as $role)
                            <x-ui.checkbox wire:key="role-option-{{ $role->value }}" :label="$role->label()" value="{{ $role->value }}" wire:model="roles" />
                        @endforeach
                    </div>
                    @error('roles') <p class="mt-1.5 text-[13px] text-danger">{{ $message }}</p> @enderror
                </div>
            </div>

            <x-ui.switch label="Compte actif" wire:model="is_active" />
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" type="button" x-on:click="open = false">Annuler</x-ui.button>
            <x-ui.button form="user-form">{{ $editingId ? 'Enregistrer' : 'Créer et inviter' }}</x-ui.button>
        </x-slot:footer>
    </x-ui.sheet>
</div>
