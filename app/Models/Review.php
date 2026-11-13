<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Avis d'un spectateur après son trajet (SP-22), rattaché au bus qui l'a transporté en dernier.
 *
 * @property int $id
 * @property int $pass_id
 * @property int $event_id
 * @property int $company_id
 * @property int|null $mission_id
 * @property int $customer_id
 * @property int $punctuality
 * @property int $comfort
 * @property int $courtesy
 * @property int $driving
 * @property int $information
 * @property string|null $comment
 * @property Carbon $created_at
 * @property-read Pass $pass
 * @property-read Event $event
 * @property-read Mission|null $mission
 * @property-read Customer $customer
 */
#[Fillable([
    'pass_id', 'event_id', 'company_id', 'mission_id', 'customer_id', 'punctuality', 'comfort', 'courtesy', 'driving', 'information', 'comment',
])]
class Review extends Model
{
    /** @use HasFactory<ReviewFactory> */
    use BelongsToCompany, HasFactory;

    /** Critères notés sur 5 (QU-02), dans l'ordre du CDC. */
    public const array CRITERIA = [
        'punctuality' => 'Ponctualité',
        'comfort' => 'Confort du bus',
        'courtesy' => 'Courtoisie du convoyeur',
        'driving' => 'Conduite du chauffeur',
        'information' => 'Information pendant le trajet',
    ];

    protected function casts(): array
    {
        return array_fill_keys(array_keys(self::CRITERIA), 'integer');
    }

    /**
     * Note globale de l'avis : moyenne des cinq critères.
     */
    public function average(): float
    {
        return round(array_sum(array_map(fn (string $criterion): int => (int) $this->getAttribute($criterion), array_keys(self::CRITERIA))) / count(self::CRITERIA), 1);
    }

    /**
     * @return BelongsTo<Pass, $this>
     */
    public function pass(): BelongsTo
    {
        return $this->belongsTo(Pass::class);
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class)->withoutGlobalScopes();
    }

    /**
     * @return BelongsTo<Mission, $this>
     */
    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class)->withoutGlobalScopes();
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
