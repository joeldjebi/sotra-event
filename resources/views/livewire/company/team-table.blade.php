<div>
    @include('partials.issued-credentials')

    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <x-ui.search wire:model.live.debounce.300ms="search" placeholder="Nom ou e-mail" />
        <x-ui.button wire:click="create" type="button" icon="sparkles">Nouveau membre</x-ui.button>
    </div>

    <x-ui.table>
        <x-slot:head>
            <th>Membre</th>
            <th>Rôle</th>
            <th>Sécurité</th>
            <th>Dernière connexion</th>
            <th></th>
        </x-slot:head>

        @forelse ($members as $member)
            <tr wire:key="member-{{ $member->id }}" @class(['hover:bg-fill-subtle', 'opacity-55' => ! $member->is_active])>
                <td>
                    <div class="flex items-center gap-3">
                        <x-ui.avatar :initials="$member->initials()" />
                        <div>
                            <p class="font-medium">{{ $member->name }} @unless ($member->is_active)<x-ui.badge class="ml-1">Désactivé</x-ui.badge>@endunless</p>
                            <p class="text-[13px] text-ink-secondary">{{ $member->email }}{{ $member->phone ? ' · '.$member->phone : '' }}</p>
                        </div>
                    </div>
                </td>
                <td>
                    @foreach ($member->businessRoles() as $role)
                        <span wire:key="member-role-{{ $member->id }}-{{ $role->value }}" class="mr-1 inline-flex"><x-ui.badge tone="brand">{{ $role->label() }}</x-ui.badge></span>
                    @endforeach
                </td>
                <td>
                    @if ($member->hasEnabledTwoFactorAuthentication())
                        <x-ui.badge tone="success">2FA active</x-ui.badge>
                    @else
                        <x-ui.badge tone="warning">2FA à activer</x-ui.badge>
                    @endif
                </td>
                <td class="text-[13px] text-ink-secondary">{{ $member->last_login_at?->diffForHumans() ?? 'Jamais' }}</td>
                <td class="text-right whitespace-nowrap">
                    <x-ui.button variant="secondary" size="sm" type="button" wire:click="edit({{ $member->id }})">Modifier</x-ui.button>
                    @if ($member->id !== auth()->id())
                        <x-ui.button :variant="$member->is_active ? 'danger-tinted' : 'tinted'" size="sm" type="button" class="ml-1"
                                     wire:click="toggleActive({{ $member->id }})" wire:confirm="{{ $member->is_active ? 'Désactiver ce compte ?' : 'Réactiver ce compte ?' }}">
                            {{ $member->is_active ? 'Désactiver' : 'Réactiver' }}
                        </x-ui.button>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5"><x-ui.empty-state icon="users" title="Aucun membre" description="Créez les comptes de la direction générale, de la direction commerciale et de la direction de l'exploitation." /></td></tr>
        @endforelse

        @if ($members->hasPages())
            <x-slot:footer>{{ $members->links() }}</x-slot:footer>
        @endif
    </x-ui.table>

    <x-ui.sheet model="showForm" :title="$editingId ? 'Modifier le membre' : 'Nouveau membre'">
        <form id="member-form" wire:submit="save" class="space-y-4">
            <x-ui.input label="Nom" name="name" wire:model="name" placeholder="Prénom Nom" />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input label="E-mail de connexion" name="email" type="email" wire:model="email" />
                <x-ui.input label="Téléphone" name="phone" type="tel" wire:model="phone" />
            </div>
            <x-ui.select label="Rôle" name="role" wire:model="role" :options="$roleOptions"
                         hint="Chaque direction ne voit que ce qui la concerne ; la direction générale consulte tout, sans rien modifier." />
            <div class="flex items-end gap-2">
                <div class="min-w-0 flex-1">
                    <x-ui.input :label="$editingId ? 'Nouveau mot de passe initial (facultatif)' : 'Mot de passe initial'" name="initial_password" wire:model="initial_password" autocomplete="off"
                                hint="Au moins 12 caractères, avec des lettres et des chiffres. À changer à la première connexion." />
                </div>
                <x-ui.button variant="secondary" type="button" wire:click="generatePassword('initial_password')" class="mb-[1px]">Générer</x-ui.button>
            </div>
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" type="button" x-on:click="open = false">Annuler</x-ui.button>
            <x-ui.button form="member-form">Enregistrer</x-ui.button>
        </x-slot:footer>
    </x-ui.sheet>
</div>
