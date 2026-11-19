<?php

namespace App\Models;

use App\Enums\EventRequestStatus;
use App\Models\Concerns\Auditable;
use App\Models\Scopes\PromoterScope;
use Database\Factories\EventRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Demande d'événement déposée par un promoteur. ACCES EVENT l'accepte en créant l'événement dans la console
 * (compagnie, grille tarifaire, commission), ou la refuse avec un motif.
 *
 * @property int $id
 * @property int $promoter_id
 * @property int|null $submitted_by
 * @property string $name
 * @property string|null $description
 * @property int|null $venue_id
 * @property string|null $venue_name
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 * @property int|null $expected_attendance
 * @property int|null $estimated_passes
 * @property string|null $desired_communes
 * @property EventRequestStatus $status
 * @property int|null $event_id
 * @property Carbon|null $reviewed_at
 * @property int|null $reviewed_by
 * @property string|null $review_note
 * @property Carbon $created_at
 * @property-read Promoter $promoter
 * @property-read Venue|null $venue
 * @property-read Event|null $event
 * @property-read User|null $submitter
 */
#[Fillable([
    'promoter_id', 'submitted_by', 'name', 'description', 'venue_id', 'venue_name', 'starts_at', 'ends_at',
    'expected_attendance', 'estimated_passes', 'desired_communes', 'status', 'event_id', 'reviewed_at', 'reviewed_by', 'review_note',
])]
class EventRequest extends Model
{
    /** @use HasFactory<EventRequestFactory> */
    use Auditable, HasFactory;

    protected static function booted(): void
    {
        static::addGlobalScope(new PromoterScope);
    }

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'expected_attendance' => 'integer',
            'estimated_passes' => 'integer',
            'status' => EventRequestStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Promoter, $this>
     */
    public function promoter(): BelongsTo
    {
        return $this->belongsTo(Promoter::class);
    }

    /**
     * @return BelongsTo<Venue, $this>
     */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function venueLabel(): string
    {
        return $this->venue === null ? ($this->venue_name ?? '—') : $this->venue->name;
    }

    public function isPending(): bool
    {
        return $this->status === EventRequestStatus::Submitted;
    }
}
