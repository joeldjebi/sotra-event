<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * SMS émis par la plateforme (journal et boîte d'envoi de développement).
 *
 * Statuts : « simulated » (pilote log), « accepted » (pris en charge par l'agrégateur), « failed » (refusé ou
 * agrégateur injoignable ; le motif est dans error).
 *
 * @property int $id
 * @property string $to
 * @property string $body
 * @property string $driver
 * @property string $status
 * @property string|null $provider_reference
 * @property int|null $segments
 * @property string|null $error
 */
class SmsMessage extends Model
{
    use MassPrunable;

    public const string SIMULATED = 'simulated';

    public const string ACCEPTED = 'accepted';

    public const string FAILED = 'failed';

    protected $guarded = ['id'];

    public function failed(): bool
    {
        return $this->status === self::FAILED;
    }

    /**
     * Purge nocturne (model:prune) au-delà de la durée de conservation (données personnelles, NF-12).
     *
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return self::query()->where('created_at', '<', now()->subDays((int) config('eventtrans.privacy.sms_retention_days')));
    }
}
