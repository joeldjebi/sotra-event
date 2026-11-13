<?php

namespace App\Models;

use App\Enums\MessageAudience;
use App\Enums\MessageStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Message du promoteur aux détenteurs de Pass (PR-04, PR-08).
 *
 * @property int $id
 * @property int $event_id
 * @property int|null $user_id
 * @property MessageAudience $audience
 * @property string|null $audience_value
 * @property string $audience_label
 * @property string $body
 * @property list<string> $channels
 * @property int $recipients_count
 * @property MessageStatus $status
 * @property Carbon|null $sent_at
 * @property Carbon $created_at
 * @property-read Event $event
 * @property-read User|null $author
 */
#[Fillable(['event_id', 'user_id', 'audience', 'audience_value', 'audience_label', 'body', 'channels', 'recipients_count', 'status', 'sent_at'])]
class PromoterMessage extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'audience' => MessageAudience::class,
            'channels' => 'array',
            'recipients_count' => 'integer',
            'status' => MessageStatus::class,
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class)->withoutGlobalScopes();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
