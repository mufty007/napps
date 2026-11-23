<?php

namespace Modules\Community\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Thread extends Model
{
    protected $table = 'community_threads';

    protected $fillable = [
        'forum_id', 'author_id', 'title', 'body', 'is_question',
        'best_comment_id', 'locked_at', 'answered_at', 'hidden_at', 'last_activity_at',
    ];

    protected function casts(): array
    {
        return [
            'is_question' => 'boolean',
            'locked_at' => 'datetime',
            'answered_at' => 'datetime',
            'hidden_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Forum, $this> */
    public function forum(): BelongsTo
    {
        return $this->belongsTo(Forum::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return HasMany<ThreadReply, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(ThreadReply::class);
    }

    /**
     * The answer the asker (or a moderator) marked best. The column predates
     * thread replies, hence its name.
     *
     * @return BelongsTo<ThreadReply, $this>
     */
    public function bestReply(): BelongsTo
    {
        return $this->belongsTo(ThreadReply::class, 'best_comment_id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'community_thread_follows')->withTimestamps();
    }

    public function isAnswered(): bool
    {
        return $this->best_comment_id !== null;
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }
}
