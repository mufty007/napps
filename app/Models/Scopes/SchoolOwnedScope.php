<?php

namespace App\Models\Scopes;

use App\Support\Scope\ActiveContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Limits any school-owned table (students, invoices, leases...) to the
 * schools the active role can see.
 */
class SchoolOwnedScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        app(ActiveContext::class)->constrainBySchoolColumn($builder, 'school_id');
    }
}
