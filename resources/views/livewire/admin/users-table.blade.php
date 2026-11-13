<div>
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-3">
            <x-ui.search wire:model.live.debounce.300ms="search" placeholder="Nom ou e-mail" />
            {{-- Contrôle segmenté façon iOS --}}
            <div class="inline-flex rounded-[10px] bg-fill p-0.5 text-[13px] font-medium">
                <button type="button" wire:click="$set('spaceFilter', '')" @class(['rounded-[8px] px-3 py-1.5 transition', 'bg-surface shadow-sm' => $spaceFilter === '', 'text-ink-secondary' => $spaceFilter !== ''])>Tous</button>
                @foreach (['admin' => 'ACCES EVENT', 'company' => 'Compagnies', 'promoter' => 'Promoteurs'] as $value => $label)
                    <button type="button" wire:key="filter-{{ $value }}" wire:click="$set('spaceFilter', '{{ $value }}')" @class(['rounded-[8px] px-3 py-1.5 transition', 'bg-surface shadow-sm' => $spaceFilter === $value, 'text-ink-secondary' => $spaceFilter !== $value])>{{ $label }}</button>
                @endforeach
            </div>
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
            <tr wire:key="user-{{ $user->id }}" @class(['hover:bg-canvas/50', 'opacity-55' => ! $user->is_active])>
                <td>
                    <div class="flex items-center gap-3">
                        <span class="grid size-9 shrink-0 place-items-center rounded-full bg-marine-900 text-[12px] font-semibold text-white">{{ $user->initials() }}</span>
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
                        <button type="button" class="ml-1 inline-grid size-8 place-items-center rounded-full text-ink-secondary hover:bg-fill" x-on:click="menu = !menu" aria-label="Plus d'actions">
                            <svg class="size-4" viewBox="0 0 24 24" fill="currentColor"><circle cx="5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="19" cy="12" r="1.6"/></svg>
                        </button>
                        <div x-cloak x-show="menu" x-on:click.outside="menu = false" x-transition.origin.top.right
                             class="absolute right-0 z-20 mt-2 w-60 rounded-2xl bg-surface p-1.5 text-left shadow-[var(--shadow-float)] ring-1 ring-hairline">
                            <button type="button" class="w-full rounded-xl px-3 py-2 text-left text-[14px] hover:bg-fill" wire:click="sendInvitation({{ $user->id }})" x-on:click="menu = false">Renvoyer le lien de connexion</button>
                            <button type="button" class="w-full rounded-xl px-3 py-2 text-left text-[14px] hover:bg-fill" wire:click="resetTwoFactor({{ $user->id }})"
                                    wire:confirm="Réinitialiser la double authentification ? L'utilisateur devra la réactiver à sa prochaine connexion." x-on:click="menu = false">Réinitialiser la 2FA</button>
                            @if ($user->id !== auth()->id())
                                <button type="button" @class(['w-full rounded-xl px-3 py-2 text-left text-[14px]', 'text-danger hover:bg-danger-soft' => $user->is_active, 'hover:bg-fill' => ! $user->is_active])
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
                    <p class="mb-1.5 text-[13px] font-medium text-ink-secondary">Rôles</p>
                    <div class="rounded-xl bg-canvas px-3 py-2">
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
