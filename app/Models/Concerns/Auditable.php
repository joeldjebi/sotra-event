<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Journalise création, modification et suppression du modèle dans audit_logs (NF-04).
 *
 * Les attributs listés dans $auditExclude (et les secrets usuels) ne sont jamais journalisés.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model): void {
            AuditLog::record('created', $model, [], self::auditableValues($model, $model->getAttributes()));
        });

        static::updated(function (Model $model): void {
            $changed = array_keys(self::auditableValues($model, $model->getChanges()));
            $changed = array_values(array_diff($changed, ['updated_at']));

            if ($changed === []) {
                return;
            }

            // Anciennes et nouvelles valeurs passent par les casts du modèle, pour être comparables.
            $old = [];
            $new = [];

            foreach ($changed as $key) {
                $old[$key] = $model->getOriginal($key);
                $new[$key] = $model->getAttributeValue($key);
            }

            AuditLog::record('updated', $model, $old, $new);
        });

        static::deleted(function (Model $model): void {
            AuditLog::record('deleted', $model, self::auditableValues($model, $model->getAttributes()), []);
        });
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private static function auditableValues(Model $model, array $values): array
    {
        $excluded = array_merge(
            ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'],
            property_exists($model, 'auditExclude') ? $model->auditExclude : [],
        );

        return array_diff_key($values, array_flip($excluded));
    }
}
