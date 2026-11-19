<div>
    @php($fcfa = fn (int $value): string => number_format($value, 0, ',', ' ').' FCFA')

    <form wire:submit="search" class="mb-6 flex flex-wrap items-end gap-3">
        <div class="w-full sm:w-96">
            <x-ui.input label="Téléphone du spectateur ou code du Pass" name="query" wire:model="query" placeholder="07 00 00 00 00 ou ET-2611-0482-YOP" autofocus autocomplete="off" />
        </div>
        <x-ui.button icon="phone" wire:loading.attr="disabled">Rechercher</x-ui.button>
    </form>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
        <div class="min-w-0 space-y-6">
            @if ($searched && ! $customer)
                <x-ui.alert tone="warning">
                    Aucun compte pour {{ $phone ? \App\Support\PhoneNumber::parse($phone)->masked() : 'ce code' }}.
                    Le spectateur n'a peut-être pas encore acheté de Pass : vous pouvez tout de même noter l'appel.
                </x-ui.alert>
            @endif

            @if ($customer)
                <x-ui.card>
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-[19px] font-semibold tracking-tight">{{ $customer->fullName() ?: 'Spectateur' }}</h2>
                            <p class="text-[14px] text-ink-secondary">{{ $customer->phone }} · client depuis {{ $customer->created_at->translatedFormat('F Y') }}{{ $customer->last_login_at ? ' · dernière connexion '.$customer->last_login_at->diffForHumans() : '' }}</p>
                        </div>
                        <x-ui.badge tone="brand">{{ $passes->count() }} Pass</x-ui.badge>
                    </div>
                </x-ui.card>

                <div class="space-y-3">
                    <h3 class="text-[17px] font-semibold tracking-tight">Pass</h3>
                    @forelse ($passes as $row)
                        @php($pass = $row['pass'])
                        <x-ui.card wire:key="cc-pass-{{ $pass->id }}">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-semibold">{{ $pass->event->name }}</p>
                                    <p class="text-[13px] text-ink-secondary">
                                        {{ $pass->code }} · {{ $pass->offer->label() }} · {{ $pass->pass_type->label() }} ·
                                        {{ $pass->boardingPoint->commune }} ({{ $pass->boardingPoint->place }}) ·
                                        départ {{ $pass->boardingPoint->departure_at->translatedFormat('j M H\hi') }}
                                    </p>
                                </div>
                                <x-ui.badge :tone="$pass->status->tone()">{{ $pass->status->label() }}</x-ui.badge>
                            </div>

                            @if ($row['mission'])
                                <p class="mt-2 text-[14px]">
                                    {{ $row['mission']->direction->label() }} :
                                    {{ $row['mission']->bus ? 'bus '.$row['mission']->bus->number : 'bus non affecté' }} ·
                                    <x-ui.badge :tone="$row['mission']->status->tone()">{{ $row['mission']->status->label() }}</x-ui.badge>
                                    @if ($row['eta'] !== null) · arrivée estimée dans {{ $row['eta'] }} min @endif
                                </p>
                            @endif
                            @if ($pass->refund)
                                <p class="mt-2 text-[14px] text-ink-secondary">Remboursement {{ $pass->refund->reference }} : {{ $fcfa($pass->refund->amount) }} · {{ $pass->refund->status->label() }}</p>
                            @endif

                            <div class="mt-3 flex flex-wrap gap-2">
                                @if (in_array($pass->status, [\App\Enums\PassStatus::Valid, \App\Enums\PassStatus::OutboundUsed, \App\Enums\PassStatus::ReturnUsed], true))
                                    <x-ui.button variant="secondary" size="sm" type="button" wire:click="resend({{ $pass->id }})" wire:confirm="Renvoyer ce Pass par SMS et WhatsApp au {{ $customer->phone }} ?">Renvoyer le Pass</x-ui.button>
                                @endif
                                @if ($pass->isTransferable())
                                    <x-ui.button variant="secondary" size="sm" type="button" wire:click="startAction({{ $pass->id }}, 'transfer')">Offrir à un autre numéro</x-ui.button>
                                @endif
                                @if ($row['quote'])
                                    <x-ui.button :variant="$row['quote']['allowed'] ? 'danger-tinted' : 'secondary'" size="sm" type="button" wire:click="startAction({{ $pass->id }}, 'cancel')" :disabled="! $row['quote']['allowed']">Annuler</x-ui.button>
                                @endif
                                <x-ui.button variant="secondary" size="sm" type="button" wire:click="startAction({{ $pass->id }}, 'complaint')">Ouvrir une réclamation</x-ui.button>
                            </div>
                            @if ($row['quote'] && ! $row['quote']['allowed'])
                                <p class="mt-2 text-[13px] text-ink-secondary">{{ $row['quote']['message'] }}</p>
                            @endif

                            @if ($actionPassId === $pass->id)
                                <div class="mt-4 rounded-[var(--radius-card)] bg-fill p-4">
                                    @switch($action)
                                        @case('transfer')
                                            <form wire:submit="transfer" class="space-y-3">
                                                <x-ui.input label="Numéro du bénéficiaire" name="transferPhone" type="tel" wire:model="transferPhone" placeholder="07 00 00 00 00"
                                                            hint="Possible tant que l'aller n'est pas scanné. L'ancien QR devient invalide." />
                                                <div class="flex gap-2"><x-ui.button size="sm">Transférer</x-ui.button><x-ui.button variant="ghost" size="sm" type="button" wire:click="cancelAction">Fermer</x-ui.button></div>
                                            </form>
                                            @break
                                        @case('cancel')
                                            <form wire:submit="cancel" class="space-y-3">
                                                <p class="text-[14px]">
                                                    Payé {{ $fcfa($row['quote']['paid']) }} · retenue {{ $fcfa($row['quote']['retained']) }} ·
                                                    <span class="font-semibold">remboursé {{ $fcfa($row['quote']['amount']) }}</span> sur le moyen de paiement d'origine.
                                                </p>
                                                <x-ui.input label="Motif donné par le spectateur" name="cancelNote" wire:model="cancelNote" />
                                                <div class="flex gap-2">
                                                    <x-ui.button variant="danger" size="sm" wire:confirm="Annuler définitivement ce Pass ?">Confirmer l'annulation</x-ui.button>
                                                    <x-ui.button variant="ghost" size="sm" type="button" wire:click="cancelAction">Fermer</x-ui.button>
                                                </div>
                                            </form>
                                            @break
                                        @case('complaint')
                                            <form wire:submit="openComplaint" class="space-y-3">
                                                <x-ui.select label="Motif" name="complaintCategory" wire:model="complaintCategory" :options="$categories" placeholder="Choisir" />
                                                <x-ui.textarea label="Ce que rapporte le spectateur" name="complaintDetails" wire:model="complaintDetails" />
                                                <div class="flex gap-2"><x-ui.button size="sm">Ouvrir la réclamation</x-ui.button><x-ui.button variant="ghost" size="sm" type="button" wire:click="cancelAction">Fermer</x-ui.button></div>
                                            </form>
                                            @break
                                    @endswitch
                                </div>
                            @endif
                        </x-ui.card>
                    @empty
                        <x-ui.card padding="p-0"><x-ui.empty-state icon="ticket" title="Aucun Pass" description="Ce spectateur n'a pas encore de Pass." /></x-ui.card>
                    @endforelse
                </div>

                @if ($orders->isNotEmpty())
                    <x-ui.table>
                        <x-slot:head><th>Commande</th><th>Événement</th><th class="text-right">Pass</th><th class="text-right">Payé</th><th>Statut</th></x-slot:head>
                        @foreach ($orders as $order)
                            <tr wire:key="cc-order-{{ $order->id }}">
                                <td><p class="tabular-nums">{{ $order->reference }}</p><p class="text-[12px] text-ink-secondary">{{ $order->created_at->format('d/m/Y H\hi') }}</p></td>
                                <td>{{ $order->event->name }}</td>
                                <td class="text-right tabular-nums">{{ $order->quantity }}</td>
                                <td class="text-right tabular-nums">{{ $fcfa((int) $order->total) }}</td>
                                <td>{{ $order->status->label() }}</td>
                            </tr>
                        @endforeach
                    </x-ui.table>
                @endif
            @endif

            @if (! $searched)
                <x-ui.card padding="p-0"><x-ui.empty-state icon="phone" title="Fiche spectateur" description="Saisissez le numéro de l'appelant ou le code de son Pass." /></x-ui.card>
            @endif
        </div>

        <div class="min-w-0 space-y-6">
            @if ($phone)
                <x-ui.card>
                    <h3 class="text-[17px] font-semibold tracking-tight">Noter l'appel</h3>
                    <form wire:submit="logCall" class="mt-4 space-y-3">
                        <x-ui.select label="Motif" name="callReason" wire:model="callReason" :options="$reasons" />
                        @if ($passes->isNotEmpty())
                            <x-ui.select label="Pass concerné (facultatif)" name="callPassId" wire:model="callPassId"
                                         :options="$passes->mapWithKeys(fn ($row) => [$row['pass']->id => $row['pass']->code.' · '.$row['pass']->event->name])->all()" placeholder="Aucun" />
                        @endif
                        <x-ui.textarea label="Notes" name="callNotes" wire:model="callNotes" rows="3" />
                        <x-ui.button class="w-full">Enregistrer l'appel</x-ui.button>
                    </form>
                </x-ui.card>
            @endif

            <x-ui.card>
                <h3 class="text-[17px] font-semibold tracking-tight">{{ $phone ? 'Historique des appels' : 'Mes derniers appels' }}</h3>
                <ul class="mt-3 divide-y divide-separator">
                    @forelse ($calls as $call)
                        <li wire:key="call-{{ $call->id }}" class="py-2.5 text-[14px]">
                            <p class="font-medium">{{ $call->reason->label() }}{{ $call->pass ? ' · '.$call->pass->code : '' }}</p>
                            @if ($call->notes)<p class="text-ink-secondary">{{ $call->notes }}</p>@endif
                            <p class="text-[12px] text-ink-tertiary">{{ $call->created_at->format('d/m H\hi') }} · {{ $call->agent?->name ?? '—' }}{{ $phone ? '' : ' · '.\App\Support\PhoneNumber::parse($call->phone)->masked() }}</p>
                        </li>
                    @empty
                        <li class="py-2.5 text-[14px] text-ink-secondary">Aucun appel enregistré.</li>
                    @endforelse
                </ul>
            </x-ui.card>
        </div>
    </div>
</div>
