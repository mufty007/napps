<?php

namespace Modules\Records\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A member of a school's staff register. Separate from platform accounts:
 * most teachers never sign in, so `user_id` is only set when they do.
 */
#[Fillable(['school_id', 'user_id', 'name', 'role_title', 'phone', 'started_at', 'left_at'])]
class StaffMember extends Model
{
    use BelongsToSchool, LogsActivity;

    protected function casts(): array
    {
        return [
            'started_at' => 'date',
            'left_at' => 'date',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasLeft(): bool
    {
        return $this->left_at !== null && $this->left_at->lte(today());
    }

    /** @param  Builder<self>  $query */
    public function scopeCurrent(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q->whereNull('left_at')->orWhere('left_at', '>', today()->toDateString()));
    }

    /** @param  Builder<self>  $query */
    public function scopeFormer(Builder $query): void
    {
        $query->whereNotNull('left_at')->where('left_at', '<=', today()->toDateString());
    }

    /**
     * Name, job title or phone.
     *
     * @param  Builder<self>  $query
     */
    public function scopeSearch(Builder $query, string $term): void
    {
        $term = trim($term);

        if ($term === '') {
            return;
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        $query->where(fn (Builder $q) => $q
            ->where('name', 'like', $like)
            ->orWhere('role_title', 'like', $like)
            ->orWhere('phone', 'like', $like));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('records')->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
