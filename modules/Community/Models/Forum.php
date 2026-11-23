<?php

namespace Modules\Community\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Forum extends Model
{
    protected $table = 'community_forums';

    protected $fillable = ['space_id', 'name', 'slug', 'category', 'description'];

    protected static function booted(): void
    {
        static::creating(function (self $forum) {
            if (blank($forum->slug)) {
                $forum->slug = Str::slug($forum->name);
            }
        });
    }

    /** @return BelongsTo<Space, $this> */
    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    /** @return HasMany<Thread, $this> */
    public function threads(): HasMany
    {
        return $this->hasMany(Thread::class);
    }
}
