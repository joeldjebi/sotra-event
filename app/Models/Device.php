<?php

namespace App\Models;

use App\Enums\DeviceStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * PDA ou smartphone utilisé sur le terrain.
 *
 * @property int $id
 * @property int $company_id
 * @property string $serial_number
 * @property string $label
 * @property string|null $model
 * @property int|null $staff_id
 * @property DeviceStatus $status
 * @property Carbon|null $blocked_at
 * @property string|null $blocked_reason
 * @property string|null $app_version
 * @property Carbon|null $last_sync_at
 * @property-read Staff|null $staff
 */
#[Fillable(['company_id', 'serial_number', 'label', 'model', 'staff_id', 'status', 'blocked_at', 'blocked_reason', 'app_version', 'last_sync_at'])]
class Device extends Model
{
    /** @use HasFactory<DeviceFactory> */
    use Auditable, BelongsToCompany, HasFactory;

    /** @var list<string> */
    public array $auditExclude = ['last_sync_at', 'app_version'];

    protected function casts(): array
    {
        return [
            'status' => DeviceStatus::class,
            'blocked_at' => 'datetime',
            'last_sync_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function isBlocked(): bool
    {
        return $this->status === DeviceStatus::Blocked;
    }
}
