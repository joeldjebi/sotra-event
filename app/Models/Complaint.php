<?php

namespace App\Models;

use App\Enums\ComplaintCategory;
use App\Enums\ComplaintStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Réclamation (SP-23, QU-03) : individuelle (un Pass) ou collective (retard de plus de 20 min d'un bus).
 *
 * @property int $id
 * @property string $number
 * @property int $event_id
 * @property int $company_id
 * @property int|null $pass_id
 * @property int|null $customer_id
 * @property int|null $mission_id
 * @property ComplaintCategory $category
 * @property string $title
 * @property string|null $details
 * @property ComplaintStatus $status
 * @property bool $is_collective
 * @property int|null $affected_count
 * @property string|null $resolution
 * @property int|null $handled_by
 * @property Carbon|null $taken_at
 * @property Carbon|null $resolved_at
 * @property Carbon $created_at
 * @property-read Event $event
 * @property-read Pass|null $pass
 * @property-read Customer|null $customer
 * @property-read Mission|null $mission
 * @property-read User|null $handler
 */
#[Fillable([
    'number', 'event_id', 'company_id', 'pass_id', 'customer_id', 'mission_id', 'category', 'title', 'details', 'status',
    'is_collective', 'affected_count', 'resolution', 'handled_by', 'taken_at', 'resolved_at',
])]
class Complaint extends Model
{
    use Auditable, BelongsToCompany;

    protected function casts(): array
    {
        return [
            'category' => ComplaintCategory::class,
            'status' => ComplaintStatus::class,
            'is_collective' => 'boolean',
            'affected_count' => 'integer',
            'taken_at' => 'datetime',
            'resolved_at' => 'datetime',
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
     * @return BelongsTo<Pass, $this>
     */
    public function pass(): BelongsTo
    {
        return $this->belongsTo(Pass::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Mission, $this>
     */
    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class)->withoutGlobalScopes();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
