<div>
    @include('partials.issued-credentials')

    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-3">
            <x-ui.search wire:model.live.debounce.300ms="search" placeholder="Rechercher un promoteur" />
            <x-ui.segmented label="Filtrer par statut">
                <x-ui.segment :active="$statusFilter === ''" wire:click="$set('statusFilter', '')">Tous</x-ui.segment>
                @foreach ($statuses as $value => $label)
                    <x-ui.segment wire:key="status-filter-{{ $value }}" :active="$statusFilter === $value" wire:click="$set('statusFilter', '{{ $value }}')">
                        {{ $value === 'en_attente' ? 'En attente' : $label }}@if ($value === 'en_attente' && $pendingCount > 0) ({{ $pendingCount }})@endif
                    </x-ui.segment>
                @endforeach
            </x-ui.segmented>
        </div>
        <x-ui.button wire:click="create" type="button" icon="sparkles">Nouveau promoteur</x-ui.button>
    </div>

    <x-ui.table>
        <x-slot:head>
            <th>Promoteur</th>
            <th>Contact</th>
            <th>Équipe</th>
            <th>Dossier</th>
            <th>Statut</th>
            <th></th>
        </x-slot:head>

        @forelse ($promoters as $promoter)
            <tr wire:key="promoter-{{ $promoter->id }}" class="hover:bg-fill-subtle">
                <td>
                    <p class="font-medium">{{ $promoter->name }}</p>
                    @if ($promoter->applied_at)
                        <p class="text-[12px] text-ink-secondary">Inscrit en ligne le {{ $promoter->applied_at->format('d/m/Y') }}</p>
                    @endif
                </td>
                <td class="text-ink-secondary">
                    <p>{{ $promoter->email ?? '—' }}</p>
                    <p class="text-[13px]">{{ $promoter->phone }}</p>
                </td>
                <td>
                    @forelse ($promoter->users as $member)
                        <span wire:key="member-{{ $member->id }}" class="mr-1 mb-1 inline-flex" title="{{ $member->primaryRole()?->label() }}">
                            <x-ui.badge :tone="$member->hasRole('admin_promoteur') ? 'brand' : 'neutral'">{{ $member->name }}</x-ui.badge>
                        </span>
                    @empty
                        <span class="text-[13px] text-ink-tertiary">Aucun compte — à créer avec « Modifier »</span>
                    @endforelse
                </td>
                <td>
                    <x-ui.badge :tone="$promoter->isFileComplete() ? 'success' : 'warning'">{{ $promoter->isFileComplete() ? 'Complet' : 'Dossier incomplet' }}</x-ui.badge>
                </td>
                <td><x-ui.badge :tone="$promoter->status->tone()">{{ $promoter->status->label() }}</x-ui.badge></td>
                <td class="text-right whitespace-nowrap">
                    <x-ui.button :variant="$promoter->status === \App\Enums\PromoterStatus::Pending ? 'primary' : 'secondary'" size="sm" type="button" wire:click="review({{ $promoter->id }})">
                        {{ $promoter->status === \App\Enums\PromoterStatus::Pending ? 'Examiner' : 'Dossier' }}
                    </x-ui.button>
                    <x-ui.button variant="secondary" size="sm" type="button" class="ml-1" wire:click="edit({{ $promoter->id }})">Modifier</x-ui.button>
                </td>
            </tr>
        @empty
            <tr><td colspan="6"><x-ui.empty-state icon="megaphone" title="Aucun promoteur" description="Ajoutez les organisateurs d'événements partenaires, ou attendez leurs inscriptions en ligne." /></td></tr>
        @endforelse

        @if ($promoters->hasPages())
            <x-slot:footer>{{ $promoters->links() }}</x-slot:footer>
        @endif
    </x-ui.table>

    <p class="mt-4 text-[13px] text-ink-secondary">
        Lien d'inscription à donner aux promoteurs : <span class="font-medium text-ink select-all">{{ route('promoters.apply') }}</span>
    </p>

    {{-- Création et modification --}}
    <x-ui.sheet model="showForm" :title="$editingId ? 'Modifier le promoteur' : 'Nouveau promoteur'">
        <form id="promoter-form" wire:submit="save" class="space-y-4">
            <x-ui.input label="Nom" name="name" wire:model="name" />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input label="E-mail" name="email" type="email" wire:model.blur="email" />
                <x-ui.input label="Téléphone" name="phone" wire:model="phone" hint="Reçoit les SMS de validation." />
            </div>
            <x-ui.input label="Adresse" name="address" wire:model="address" />
            <x-ui.input label="Numéro RCCM (facultatif)" name="rccm_number" wire:model="rccm_number" />
            @foreach (['rccm_document' => 'Extrait RCCM (facultatif)', 'identity_document' => 'Pièce d\'identité du responsable (facultatif)'] as $field => $label)
                <div wire:key="admin-upload-{{ $field }}">
                    <label for="{{ $field }}" class="block px-0.5 text-footnote font-medium text-ink-secondary">{{ $label }}</label>
                    <input id="{{ $field }}" type="file" wire:model="{{ $field }}" accept=".pdf,image/jpeg,image/png" class="mt-1.5 block w-full text-[14px]">
                    @error($field)<p class="mt-1 text-footnote text-danger">{{ $message }}</p>@enderror
                </div>
            @endforeach

            @unless ($editingHasAccount)
                <div class="space-y-4 rounded-[var(--radius-card)] bg-fill p-4">
                    <x-ui.switch label="Créer le compte de l'administrateur du promoteur" wire:model.live="createAccount" />
                    @if ($createAccount)
                        <p class="text-[13px] text-ink-secondary">Il pourra créer lui-même les comptes de son équipe et proposer ses événements.</p>
                        <x-ui.input label="Nom de l'administrateur" name="account_name" wire:model="account_name" placeholder="Prénom Nom" />
                        <x-ui.input label="E-mail de connexion" name="account_email" type="email" wire:model="account_email" />
                        <div class="flex items-end gap-2">
                            <div class="min-w-0 flex-1">
                                <x-ui.input label="Mot de passe" name="account_password" wire:model="account_password" autocomplete="off"
                                            hint="Au moins 12 caractères, avec des lettres et des chiffres. Il devra le changer à sa première connexion." />
                            </div>
                            <x-ui.button variant="secondary" type="button" wire:click="generatePassword('account_password')" class="mb-[1px]">Générer</x-ui.button>
                        </div>
                    @endif
                </div>
            @endunless
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" type="button" x-on:click="open = false">Annuler</x-ui.button>
            <x-ui.button form="promoter-form">Enregistrer</x-ui.button>
        </x-slot:footer>
    </x-ui.sheet>

    {{-- Examen du dossier et décision --}}
    <x-ui.sheet model="showReview" :title="$reviewing?->name ?? 'Dossier'" :subtitle="$reviewing?->status->label()">
        @if ($reviewing)
            <div class="space-y-5 text-[14px]">
                <dl class="grid gap-3 sm:grid-cols-2">
                    <div><dt class="text-ink-tertiary">Téléphone</dt><dd>{{ $reviewing->phone ?? '—' }} @if ($reviewing->phone_verified_at)<span class="text-success">· vérifié</span>@endif</dd></div>
                    <div><dt class="text-ink-tertiary">E-mail</dt><dd class="break-all">{{ $reviewing->email ?? '—' }}</dd></div>
                    <div><dt class="text-ink-tertiary">Adresse</dt><dd>{{ $reviewing->address ?? '—' }}</dd></div>
                    <div><dt class="text-ink-tertiary">Numéro RCCM</dt><dd>{{ $reviewing->rccm_number ?? '—' }}</dd></div>
                    <div><dt class="text-ink-tertiary">Origine</dt><dd>{{ $reviewing->applied_at ? 'Inscription en ligne, le '.$reviewing->applied_at->format('d/m/Y à H\hi') : 'Créé par ACCES EVENT' }}</dd></div>
                    <div><dt class="text-ink-tertiary">Demandes d'événement</dt><dd>{{ $reviewing->event_requests_count }}</dd></div>
                </dl>

                <div>
                    <div class="flex items-center justify-between">
                        <p class="font-semibold">Justificatifs</p>
                        <x-ui.badge :tone="$reviewing->isFileComplete() ? 'success' : 'warning'">{{ $reviewing->isFileComplete() ? 'Complet' : 'Dossier incomplet' }}</x-ui.badge>
                    </div>
                    <ul class="mt-2 space-y-1.5">
                        @foreach (\App\Models\Promoter::DOCUMENTS as $key => $document)
                            <li wire:key="doc-{{ $key }}" class="flex items-center justify-between gap-3 rounded-[10px] bg-fill px-3 py-2">
                                <span>{{ $document['label'] }}</span>
                                @if ($reviewing->{$document['column']})
                                    <a href="{{ route('admin.promoters.document', [$reviewing, $key]) }}" target="_blank" class="font-medium text-brand-text hover:underline">Ouvrir</a>
                                @else
                                    <span class="text-ink-tertiary">Non fourni</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div>
                    <p class="font-semibold">Équipe</p>
                    <ul class="mt-2 space-y-1">
                        @forelse ($reviewing->users as $member)
                            <li wire:key="review-member-{{ $member->id }}">{{ $member->name }} · {{ $member->email }} <span class="text-ink-secondary">({{ $member->primaryRole()?->label() }})</span></li>
                        @empty
                            <li class="text-ink-tertiary">Aucun compte</li>
                        @endforelse
                    </ul>
                </div>

                @if ($reviewing->reviewed_at)
                    <p class="text-ink-secondary">
                        Dernière décision le {{ $reviewing->reviewed_at->format('d/m/Y à H\hi') }}{{ $reviewing->reviewer ? ' par '.$reviewing->reviewer->name : '' }}{{ $reviewing->review_note ? ' : '.$reviewing->review_note : '.' }}
                    </p>
                @endif

                @if (in_array($reviewing->status, [\App\Enums\PromoterStatus::Pending, \App\Enums\PromoterStatus::Approved], true))
                    <x-ui.textarea :label="$reviewing->status === \App\Enums\PromoterStatus::Pending ? 'Motif du refus (envoyé au promoteur par SMS)' : 'Motif de la suspension (envoyé au promoteur par SMS)'" name="decisionNote" rows="2" wire:model="decisionNote" />
                @endif
                @error('status')<p class="text-danger">{{ $message }}</p>@enderror
            </div>
        @endif
        <x-slot:footer>
            <x-ui.button variant="secondary" type="button" x-on:click="open = false">Fermer</x-ui.button>
            @if ($reviewing)
                @switch($reviewing->status)
                    @case(\App\Enums\PromoterStatus::Pending)
                        <x-ui.button variant="danger-tinted" type="button" wire:click="reject">Refuser</x-ui.button>
                        <x-ui.button type="button" wire:click="approve" wire:confirm="{{ $reviewing->isFileComplete() ? 'Valider ce promoteur ?' : 'Le dossier est incomplet. Valider quand même ce promoteur ?' }}">Valider</x-ui.button>
                        @break
                    @case(\App\Enums\PromoterStatus::Approved)
                        <x-ui.button variant="danger" type="button" wire:click="suspend" wire:confirm="Suspendre ce promoteur ? Son équipe n'aura plus accès à l'espace promoteur.">Suspendre</x-ui.button>
                        @break
                    @case(\App\Enums\PromoterStatus::Suspended)
                        <x-ui.button type="button" wire:click="reactivate">Réactiver</x-ui.button>
                        @break
                    @case(\App\Enums\PromoterStatus::Rejected)
                        <x-ui.button type="button" wire:click="approve" wire:confirm="Revenir sur le refus et valider ce promoteur ?">Valider finalement</x-ui.button>
                        @break
                @endswitch
            @endif
        </x-slot:footer>
    </x-ui.sheet>
</div>
