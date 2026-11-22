<?php

namespace App\Models\Concerns;

use App\Models\School;
use App\Models\Scopes\SchoolOwnedScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Use on every model whose table has a school_id column. Adds the org-scope
 * global scope so queries only return rows the active role may see.
 *
 * @mixin Model
 */
trait BelongsToSchool
{
    public static function bootBelongsToSchool(): void
    {
        static::addGlobalScope(new SchoolOwnedScope);
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
