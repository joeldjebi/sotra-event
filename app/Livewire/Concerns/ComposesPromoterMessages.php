<?php

namespace App\Livewire\Concerns;

use App\Enums\MessageAudience;
use App\Enums\Permission;
use App\Models\Event;
use App\Models\User;
use App\Services\Promoter\PromoterMessenger;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Bloc « Message aux spectateurs » (PR-04) : destinataires, message, canaux.
 */
trait ComposesPromoterMessages
{
    public string $audience = 'tous';

    public string $audienceValue = '';

    public string $body = '';

    /** @var array<int, string> Cases cochées : Livewire peut laisser des trous dans les clés. */
    public array $channels = ['push', 'sms', 'whatsapp'];

    public function updatedAudience(): void
    {
        $this->audienceValue = '';
    }

    public function sendMessage(PromoterMessenger $messenger): void
    {
        Gate::authorize(Permission::PromoterMessagesSend->value);
        $event = $this->selectedEvent() ?? abort(404);

        $this->validate([
            'audience' => ['required', Rule::enum(MessageAudience::class)],
            'audienceValue' => [Rule::requiredIf($this->audience !== MessageAudience::All->value), 'nullable', 'string'],
            'body' => ['required', 'string', 'min:5', 'max:'.(int) config('eventtrans.promoter_messages.max_length')],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => [Rule::in(PromoterMessenger::CHANNELS)],
        ], ['channels.required' => 'Choisissez au moins un canal.'], ['audienceValue' => 'destinataires', 'body' => 'message']);

        /** @var User $user */
        $user = auth()->user();
        $message = $messenger->send($event, $user, MessageAudience::from($this->audience), $this->audienceValue ?: null, trim($this->body), array_values($this->channels));

        $this->reset(['body', 'audienceValue']);
        $this->audience = MessageAudience::All->value;
        $this->notify("Message envoyé à {$message->recipients_count} spectateur(s).", $message->recipients_count > 0 ? 'success' : 'warning');
    }

    /**
     * Données du bloc d'envoi pour la vue.
     *
     * @return array{audiences: array<string, string>, targets: array<int|string, string>, recipients: int, remaining: int, maxLength: int}
     */
    protected function composerData(Event $event): array
    {
        $messenger = app(PromoterMessenger::class);
        $audience = MessageAudience::tryFrom($this->audience) ?? MessageAudience::All;

        return [
            'audiences' => MessageAudience::options(),
            'targets' => $audience === MessageAudience::All ? [] : $messenger->targets($event)[$audience->value],
            'recipients' => $audience !== MessageAudience::All && $this->audienceValue === '' ? 0 : $messenger->countRecipients($event, $audience, $this->audienceValue ?: null),
            'remaining' => $messenger->remaining($event),
            'maxLength' => (int) config('eventtrans.promoter_messages.max_length'),
        ];
    }
}
