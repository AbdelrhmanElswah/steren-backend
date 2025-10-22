<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class ExcludeSoftDeletedScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        // Only apply if the model uses SoftDeletes trait
        if (in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses_recursive($model))) {
            $builder->whereNull($model->getQualifiedDeletedAtColumn());
        }
    }
}
