<?php

namespace Modules\Community\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * An answer or reply in a forum thread (COM-2). Members upvote useful ones;
 * on a question the asker or a moderator marks the best answer.
 */
#[Fillable(['thread_id', 'author_id', 'body', 'votes_count', 'hidden_at'])]
class ThreadReply extends Model
{
    protected $table = 'community_thread_replies';

    protected function casts(): array
    {
        return [
            'votes_count' => 'integer',
            'hidden_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Thread, $this>
     */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function voters(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'community_votes')->withTimestamps();
    }
}
