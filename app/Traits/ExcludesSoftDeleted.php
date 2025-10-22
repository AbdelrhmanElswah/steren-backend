<?php

namespace App\Traits;

use App\Scopes\ExcludeSoftDeletedScope;

trait ExcludesSoftDeleted
{
    /**
     * Boot the trait and apply the global scope
     */
    protected static function bootExcludesSoftDeleted()
    {
        static::addGlobalScope(new ExcludeSoftDeletedScope);
    }
}
