<?php

namespace Modules\Community\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Post extends Model
{
    protected $table = 'community_posts';

    protected $fillable = [
        'space_id', 'author_id', 'type', 'body', 'pinned', 'pinned_by', 'hidden_at', 'last_activity_at',
    ];

    protected function casts(): array
    {
        return [
            'pinned' => 'boolean',
            'hidden_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Space, $this> */
    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return HasOne<Poll, $this> */
    public function poll(): HasOne
    {
        return $this->hasOne(Poll::class);
    }

    /** @return HasMany<Comment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /** @return MorphMany<Reaction, $this> */
    public function reactions(): MorphMany
    {
        return $this->morphMany(Reaction::class, 'reactable');
    }

    /** @param  Builder<self>  $query */
    public function scopeVisible(Builder $query): void
    {
        $query->whereNull('hidden_at');
    }

    public function likedBy(User $user): bool
    {
        return $this->reactions()->where('user_id', $user->id)->where('type', 'like')->exists();
    }
}
