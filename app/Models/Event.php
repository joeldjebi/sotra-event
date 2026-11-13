<?php

namespace App\Models;

use App\Enums\EventStatus;
use App\Enums\Offer;
use App\Enums\SalesChannel;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Scopes\PromoterScope;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Événement desservi par EVENT TRANS.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int $venue_id
 * @property int $promoter_id
 * @property int|null $transport_lead_id
 * @property int $company_id
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property Carbon|null $doors_open_at
 * @property int|null $estimated_passes
 * @property string $commission_rate
 * @property string $fee_rate
 * @property list<string> $channels
 * @property EventStatus $status
 * @property string|null $visual_path
 * @property bool $featured
 * @property Carbon|null $proposed_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $sale_opened_at
 * @property-read Venue $venue
 * @property-read Promoter $promoter
 * @property-read Company $company
 * @property-read User|null $transportLead
 */
#[Fillable([
    'name', 'slug', 'description', 'venue_id', 'promoter_id', 'transport_lead_id', 'company_id',
    'starts_at', 'ends_at', 'doors_open_at', 'estimated_passes', 'commission_rate', 'fee_rate',
    'channels', 'status', 'visual_path', 'featured', 'created_by', 'proposed_at', 'accepted_at', 'sale_opened_at',
])]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use Auditable, BelongsToCompany, HasFactory;

    protected static function booted(): void
    {
        static::addGlobalScope(new PromoterScope);
    }

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'doors_open_at' => 'datetime',
            'estimated_passes' => 'integer',
            'commission_rate' => 'decimal:2',
            'fee_rate' => 'decimal:2',
            'channels' => 'array',
            'status' => EventStatus::class,
            'featured' => 'boolean',
            'proposed_at' => 'datetime',
            'accepted_at' => 'datetime',
            'sale_opened_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Venue, $this>
     */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /**
     * @return BelongsTo<Promoter, $this>
     */
    public function promoter(): BelongsTo
    {
        return $this->belongsTo(Promoter::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function transportLead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transport_lead_id');
    }

    /**
     * @return HasMany<EventOffer, $this>
     */
    public function offers(): HasMany
    {
        return $this->hasMany(EventOffer::class);
    }

    /**
     * @return HasMany<BoardingPoint, $this>
     */
    public function boardingPoints(): HasMany
    {
        return $this->hasMany(BoardingPoint::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @return HasMany<EventPrice, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(EventPrice::class);
    }

    /**
     * @return HasMany<DropZone, $this>
     */
    public function dropZones(): HasMany
    {
        return $this->hasMany(DropZone::class);
    }

    /**
     * @return HasMany<EventNegotiation, $this>
     */
    public function negotiations(): HasMany
    {
        return $this->hasMany(EventNegotiation::class)->orderBy('created_at')->orderBy('id');
    }

    /**
     * @return HasMany<Mission, $this>
     */
    public function missions(): HasMany
    {
        return $this->hasMany(Mission::class);
    }

    /**
     * @return HasMany<EventReserveBus, $this>
     */
    public function reserveBuses(): HasMany
    {
        return $this->hasMany(EventReserveBus::class);
    }

    /**
     * @return HasMany<EventTechTeam, $this>
     */
    public function techTeams(): HasMany
    {
        return $this->hasMany(EventTechTeam::class);
    }

    /**
     * @return HasMany<Review, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * @return HasMany<Complaint, $this>
     */
    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    /**
     * @return HasMany<PromoterMessage, $this>
     */
    public function promoterMessages(): HasMany
    {
        return $this->hasMany(PromoterMessage::class);
    }

    /**
     * @return list<Offer>
     */
    public function activeOffers(): array
    {
        $values = $this->offers->pluck('offer')->map(fn ($offer) => $offer instanceof Offer ? $offer->value : $offer)->all();

        return array_values(array_filter(Offer::cases(), fn (Offer $offer): bool => in_array($offer->value, $values, true)));
    }

    public function hasChannel(SalesChannel $channel): bool
    {
        return in_array($channel->value, $this->channels, true);
    }

    /**
     * Zones tarifaires utilisées par au moins un point d'embarquement.
     *
     * @return list<int>
     */
    public function usedZones(): array
    {
        return $this->boardingPoints->pluck('zone')->unique()->sort()->values()->map(fn ($zone) => (int) $zone)->all();
    }

    public function visualUrl(): ?string
    {
        return $this->visual_path ? Storage::disk('public')->url($this->visual_path) : null;
    }
}
