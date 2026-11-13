<div>
    <div class="mb-6 w-full sm:w-80">
        <x-ui.select label="Événement" name="eventId" wire:model.live="eventId" :options="$eventOptions" placeholder="Choisir un événement" />
    </div>

    @if (! $event)
        <x-ui.card padding="p-0"><x-ui.empty-state icon="chat" title="Aucun événement" description="Les messages s'envoient aux détenteurs de Pass d'un événement en vente." /></x-ui.card>
    @else
        <div class="grid gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]">
            <div>@include('livewire.promoter.partials.composer')</div>

            <x-ui.table>
                <x-slot:head>
                    <th>Envoyé</th>
                    <th>Destinataires</th>
                    <th>Message</th>
                    <th class="text-right">Spectateurs</th>
                    <th>Statut</th>
                </x-slot:head>
                @forelse ($messages as $message)
                    <tr wire:key="message-{{ $message->id }}">
                        <td class="whitespace-nowrap">
                            <p>{{ $message->created_at->format('d/m H\hi') }}</p>
                            <p class="text-[12px] text-ink-secondary">{{ $message->author?->name }}</p>
                        </td>
                        <td>
                            <p>{{ $message->audience_label }}</p>
                            <p class="text-[12px] text-ink-secondary">{{ collect($message->channels)->map(fn ($channel) => ['push' => 'Notification', 'sms' => 'SMS', 'whatsapp' => 'WhatsApp'][$channel] ?? $channel)->join(' · ') }}</p>
                        </td>
                        <td class="max-w-80 text-[13px]">{{ $message->body }}</td>
                        <td class="text-right tabular-nums">{{ number_format($message->recipients_count, 0, ',', ' ') }}</td>
                        <td><x-ui.badge :tone="$message->status->tone()">{{ $message->status->label() }}</x-ui.badge></td>
                    </tr>
                @empty
                    <tr><td colspan="5"><x-ui.empty-state icon="chat" title="Aucun message envoyé" description="L'historique de vos messages aux spectateurs s'affichera ici." /></td></tr>
                @endforelse
            </x-ui.table>
        </div>
    @endif
</div>
