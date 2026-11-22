<?php

namespace App\Models\Scopes;

use App\Support\Scope\ActiveContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Limits the schools table to the active role's part of the org chain.
 * Public pages (verify a school, directory) must opt out explicitly.
 */
class SchoolVisibilityScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        app(ActiveContext::class)->constrainSchools($builder);
    }
}
