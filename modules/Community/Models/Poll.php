<?php

namespace Modules\Community\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property bool $multiple
 * @property bool $anonymous
 * @property bool $formal
 * @property Carbon|null $closes_at
 * @property Carbon|null $closed_at
 */
#[Fillable(['post_id', 'multiple', 'anonymous', 'formal', 'closes_at', 'closed_at', 'closed_by'])]
class Poll extends Model
{
    protected $table = 'community_polls';

    protected function casts(): array
    {
        return [
            'multiple' => 'boolean',
            'anonymous' => 'boolean',
            'formal' => 'boolean',
            'closes_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Post, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /** @return HasMany<PollOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(PollOption::class)->orderBy('position')->orderBy('id');
    }

    /** @return HasMany<PollVote, $this> */
    public function votes(): HasMany
    {
        return $this->hasMany(PollVote::class);
    }

    /** @return BelongsTo<User, $this> */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function isClosed(): bool
    {
        return $this->closed_at !== null || ($this->closes_at !== null && $this->closes_at->isPast());
    }

    /**
     * When voting ended: the manual close, or the deadline once it has passed.
     */
    public function endedAt(): ?Carbon
    {
        return $this->closed_at ?? ($this->closes_at?->isPast() ? $this->closes_at : null);
    }
}
