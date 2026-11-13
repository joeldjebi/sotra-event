<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Cloisonnement : un utilisateur rattaché à un promoteur ne voit que les données de son promoteur.
 *
 * @implements Scope<Model>
 */
class PromoterScope implements Scope
{
    /**
     * @param  Builder<covariant Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $promoterId = Auth::user()?->getAttribute('promoter_id');

        if ($promoterId !== null) {
            $builder->where($model->qualifyColumn('promoter_id'), (int) $promoterId);
        }
    }
}
