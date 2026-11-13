<?php

namespace App\Models;

use App\Enums\NegotiationSubject;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Message d'un échange de négociation (CO-04). side : « acces_event » ou « company ».
 *
 * @property int $id
 * @property int $event_id
 * @property int|null $user_id
 * @property string $side
 * @property NegotiationSubject $subject
 * @property string|null $message
 * @property Carbon $created_at
 * @property-read User|null $user
 */
#[Fillable(['event_id', 'user_id', 'side', 'subject', 'message'])]
class EventNegotiation extends Model
{
    public const UPDATED_AT = null;

    public const SIDE_ACCES_EVENT = 'acces_event';

    public const SIDE_COMPANY = 'company';

    protected function casts(): array
    {
        return [
            'subject' => NegotiationSubject::class,
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
