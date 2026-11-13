<?php

namespace App\Models;

use App\Enums\ScanReason;
use App\Enums\ScanResult;
use App\Enums\Segment;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Scan d'un Pass par un convoyeur.
 *
 * @property int $id
 * @property string $client_uuid
 * @property int|null $pass_id
 * @property int $mission_id
 * @property int $staff_id
 * @property int|null $device_id
 * @property Segment $segment
 * @property string $input
 * @property ScanResult $result
 * @property ScanReason|null $reason
 * @property bool $offline
 * @property bool $conflict
 * @property Carbon $scanned_at
 * @property-read Pass|null $pass
 * @property-read Mission $mission
 */
#[Fillable(['client_uuid', 'pass_id', 'mission_id', 'staff_id', 'device_id', 'segment', 'input', 'result', 'reason', 'offline', 'conflict', 'scanned_at'])]
class Scan extends Model
{
    protected function casts(): array
    {
        return [
            'segment' => Segment::class,
            'result' => ScanResult::class,
            'reason' => ScanReason::class,
            'offline' => 'boolean',
            'conflict' => 'boolean',
            'scanned_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Pass, $this>
     */
    public function pass(): BelongsTo
    {
        return $this->belongsTo(Pass::class);
    }

    /**
     * @return BelongsTo<Mission, $this>
     */
    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }
}
