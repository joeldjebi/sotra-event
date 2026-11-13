<?php

namespace App\Services\Promoter;

use App\Contracts\PushSender;
use App\Contracts\SmsSender;
use App\Contracts\WhatsAppSender;
use App\Enums\MessageAudience;
use App\Enums\MessageStatus;
use App\Enums\Offer;
use App\Enums\PassStatus;
use App\Models\BoardingPoint;
use App\Models\Event;
use App\Models\Mission;
use App\Models\Pass;
use App\Models\PromoterMessage;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Messages du promoteur aux détenteurs de Pass (PR-04) : ciblage (tous, point, bus, offre), envoi par
 * notification, SMS et WhatsApp, un message par spectateur même s'il détient plusieurs Pass. Journal (PR-08)
 * et plafond par événement (config eventtrans.promoter_messages.max_per_event).
 */
class PromoterMessenger
{
    public const array CHANNELS = ['push', 'sms', 'whatsapp'];

    public function __construct(
        private readonly SmsSender $sms,
        private readonly WhatsAppSender $whatsApp,
        private readonly PushSender $push,
    ) {}

    public function remaining(Event $event): int
    {
        return max(0, (int) config('eventtrans.promoter_messages.max_per_event') - $event->promoterMessages()->count());
    }

    /**
     * Nombre de spectateurs (numéros distincts) touchés par un ciblage.
     */
    public function countRecipients(Event $event, MessageAudience $audience, ?string $value): int
    {
        return $this->passes($event, $audience, $value)->distinct()->count('holder_id');
    }

    /**
     * @param  list<string>  $channels
     */
    public function send(Event $event, User $author, MessageAudience $audience, ?string $value, string $body, array $channels): PromoterMessage
    {
        if ($this->remaining($event) === 0) {
            throw ValidationException::withMessages(['body' => 'Plafond de messages atteint pour cet événement.']);
        }

        $label = $this->audienceLabel($event, $audience, $value);
        $passes = $this->passes($event, $audience, $value)->with('holder')->get()->unique('holder_id');
        $text = "{$event->name} : {$body}";

        foreach ($passes as $pass) {
            $phone = PhoneNumber::parse($pass->holder->phone);

            if (in_array('sms', $channels, true)) {
                $this->sms->send($phone, "EVENT TRANS · {$text}");
            }

            if (in_array('whatsapp', $channels, true)) {
                $this->whatsApp->send($phone, 'promoter_message', $text, ['event' => $event->id]);
            }

            if (in_array('push', $channels, true)) {
                $this->push->send($pass->holder, $event->name, $body, ['event_id' => $event->id, 'code' => $pass->code]);
            }
        }

        return $event->promoterMessages()->create([
            'user_id' => $author->id,
            'audience' => $audience,
            'audience_value' => $value,
            'audience_label' => $label,
            'body' => $body,
            'channels' => $channels,
            'recipients_count' => $passes->count(),
            'status' => $passes->isEmpty() ? MessageStatus::NoRecipient : MessageStatus::Sent,
            'sent_at' => now(),
        ]);
    }

    /**
     * Cibles possibles pour l'événement, par type de destinataires.
     *
     * @return array{point: array<int, string>, bus: array<int, string>, offre: array<string, string>}
     */
    public function targets(Event $event): array
    {
        return [
            'point' => $event->boardingPoints()->orderBy('position')->get()
                ->mapWithKeys(fn (BoardingPoint $point) => [$point->id => "{$point->commune} · {$point->place}"])->all(),
            'bus' => Mission::query()->withoutGlobalScopes()->with(['bus', 'boardingPoint'])->where('event_id', $event->id)
                ->whereNotNull('bus_id')->orderBy('direction')->orderBy('boarding_point_id')->get()
                ->mapWithKeys(fn (Mission $mission) => [$mission->id => "Bus {$mission->bus?->number} · {$mission->boardingPoint->commune} · {$mission->direction->label()}"])->all(),
            'offre' => collect($event->activeOffers())->mapWithKeys(fn (Offer $offer) => [$offer->value => $offer->label()])->all(),
        ];
    }

    private function audienceLabel(Event $event, MessageAudience $audience, ?string $value): string
    {
        if ($audience === MessageAudience::All) {
            return $audience->label();
        }

        $targets = $this->targets($event)[$audience->value];

        return $targets[$value] ?? throw ValidationException::withMessages(['audienceValue' => 'Choisissez les destinataires.']);
    }

    /**
     * @return Builder<Pass>
     */
    private function passes(Event $event, MessageAudience $audience, ?string $value): Builder
    {
        $query = Pass::query()->where('event_id', $event->id)->whereIn('status', PassStatus::sold());

        return match ($audience) {
            MessageAudience::All => $query,
            MessageAudience::Point => $query->where('boarding_point_id', (int) $value),
            MessageAudience::Bus => $query->where(fn ($inner) => $inner->where('outbound_mission_id', (int) $value)->orWhere('return_mission_id', (int) $value)),
            MessageAudience::Offer => $query->where('offer', (string) $value),
        };
    }
}
