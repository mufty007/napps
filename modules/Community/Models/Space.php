<?php

namespace Modules\Community\Models;

use App\Models\Chapter;
use App\Models\State;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Space extends Model
{
    protected $table = 'community_spaces';

    protected $fillable = [
        'type', 'name', 'slug', 'privacy', 'description',
        'state_id', 'chapter_id', 'created_by',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $space) {
            if (blank($space->slug)) {
                $space->slug = Str::slug($space->name).'-'.Str::lower(Str::random(4));
            }
        });
    }

    /** @return BelongsTo<Chapter, $this> */
    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }

    /** @return BelongsTo<State, $this> */
    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'community_space_members')
            ->withPivot(['role', 'status'])
            ->withTimestamps();
    }

    /** @return HasMany<Post, $this> */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    /** @return HasMany<Forum, $this> */
    public function forums(): HasMany
    {
        return $this->hasMany(Forum::class);
    }

    public function isChapterSpace(): bool
    {
        return $this->type === 'chapter';
    }

    /**
     * Chapter, state and national spaces: membership follows NAPPS roles.
     */
    public function isAutomatic(): bool
    {
        return in_array($this->type, ['chapter', 'state', 'national'], true);
    }

    /**
     * Interest and committee groups: membership is joined, requested or invited.
     */
    public function isGroup(): bool
    {
        return ! $this->isAutomatic();
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'chapter' => __('Chapter'),
            'state' => __('State'),
            'national' => __('National'),
            'committee' => __('Committee'),
            default => __('Interest group'),
        };
    }

    public function privacyLabel(): string
    {
        return match ($this->privacy) {
            'request' => __('Request to join'),
            'invite' => __('Invite only'),
            default => __('Open'),
        };
    }

    /**
     * Members whose membership is confirmed (not pending or merely invited).
     *
     * @return BelongsToMany<User, $this>
     */
    public function activeMembers(): BelongsToMany
    {
        return $this->members()->wherePivot('status', 'active');
    }
}
