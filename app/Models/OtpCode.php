<?php

namespace App\Models;

use App\Enums\OtpAudience;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Code de connexion à usage unique (haché).
 *
 * @property int $id
 * @property string $phone
 * @property OtpAudience $audience
 * @property string $code_hash
 * @property int $attempts
 * @property Carbon $expires_at
 * @property Carbon|null $consumed_at
 * @property Carbon $created_at
 */
class OtpCode extends Model
{
    use MassPrunable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'audience' => OtpAudience::class,
            'attempts' => 'integer',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Purge nocturne (model:prune) au-delà de la durée de conservation (données personnelles, NF-12).
     *
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return self::query()->where('created_at', '<', now()->subDays((int) config('eventtrans.privacy.otp_retention_days')));
    }
}
