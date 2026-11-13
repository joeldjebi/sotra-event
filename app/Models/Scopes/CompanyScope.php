<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Cloisonnement : un acteur rattaché à une compagnie ne voit que les données de sa compagnie.
 *
 * @implements Scope<Model>
 */
class CompanyScope implements Scope
{
    /**
     * @param  Builder<covariant Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $companyId = self::currentCompanyId();

        if ($companyId !== null) {
            $builder->where($model->qualifyColumn('company_id'), $companyId);
        }
    }

    public static function currentCompanyId(): ?int
    {
        $actor = Auth::user();

        if ($actor === null) {
            return null;
        }

        $companyId = $actor->getAttribute('company_id');

        return $companyId === null ? null : (int) $companyId;
    }
}
