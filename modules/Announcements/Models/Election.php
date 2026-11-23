<?php

namespace Modules\Announcements\Models;

use App\Enums\MembershipStatus;
use App\Enums\ScopeLevel;
use App\Models\Chapter;
use App\Models\School;
use App\Models\State;
use App\Models\User;
use App\Support\Scope\ActiveContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A one-school-one-vote election run by a chapter (all its schools vote) or a
 * state (every school in the state votes).
 */
class Election extends Model
{
    public const DRAFT = 'draft';

    public const OPEN = 'open';

    public const CLOSED = 'closed';

    protected $fillable = [
        'title', 'description', 'state_id', 'chapter_id', 'status', 'closes_at', 'created_by',
    ];

    protected function casts(): array
    {
        return ['closes_at' => 'datetime'];
    }

    /** @return BelongsTo<State, $this> */
    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    /** @return BelongsTo<Chapter, $this> */
    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<ElectionCandidate, $this> */
    public function candidates(): HasMany
    {
        return $this->hasMany(ElectionCandidate::class)->orderBy('sort')->orderBy('id');
    }

    /** @return HasMany<ElectionVote, $this> */
    public function votes(): HasMany
    {
        return $this->hasMany(ElectionVote::class);
    }

    public function isDraft(): bool
    {
        return $this->status === self::DRAFT;
    }

    /** Open and not past its closing time. */
    public function isOpen(): bool
    {
        return $this->status === self::OPEN
            && ($this->closes_at === null || $this->closes_at->isFuture());
    }

    /** Closed by hand, or open but past its closing time. */
    public function isClosed(): bool
    {
        return ! $this->isDraft() && ! $this->isOpen();
    }

    public function statusLabel(): string
    {
        return match (true) {
            $this->isDraft() => __('Draft'),
            $this->isOpen() => __('Voting open'),
            default => __('Closed'),
        };
    }

    public function statusTone(): string
    {
        return match (true) {
            $this->isDraft() => 'neutral',
            $this->isOpen() => 'success',
            default => 'warning',
        };
    }

    /** Who votes: "Kano Chapter" or "Kano State". Relations must be loaded. */
    public function electorateLabel(): string
    {
        return $this->chapter_id
            ? (string) $this->chapter?->name
            : __(':name State', ['name' => (string) $this->state?->name]);
    }

    /** Only active member schools in the electorate vote (owing and suspended schools do not). */
    public function isEligible(School $school): bool
    {
        if ($school->membership_status !== MembershipStatus::Active) {
            return false;
        }

        return $this->chapter_id
            ? $school->chapter_id === $this->chapter_id
            : $school->state_id === $this->state_id;
    }

    /**
     * Schools in the electorate (unscoped, so a voter sees the whole electorate count).
     *
     * @return Builder<School>
     */
    public function electorate(): Builder
    {
        return School::withoutGlobalScopes()
            ->where('membership_status', MembershipStatus::Active->value)
            ->when($this->chapter_id, fn (Builder $q) => $q->where('chapter_id', $this->chapter_id))
            ->when(! $this->chapter_id, fn (Builder $q) => $q->where('state_id', $this->state_id));
    }

    public function hasVoted(School $school): bool
    {
        return $this->votes()->where('school_id', $school->id)->exists();
    }

    /**
     * Elections a school may see: its chapter's and its state's, once out of draft.
     *
     * @param  Builder<self>  $query
     */
    public function scopeForSchool(Builder $query, School $school): void
    {
        $query->where('status', '!=', self::DRAFT)
            ->where('state_id', $school->state_id)
            ->where(fn (Builder $q) => $q->whereNull('chapter_id')->orWhere('chapter_id', $school->chapter_id));
    }

    /**
     * Elections the active executive role runs.
     *
     * @param  Builder<self>  $query
     */
    public function scopeManageableBy(Builder $query, ActiveContext $context): void
    {
        match ($context->level()) {
            ScopeLevel::National => null,
            ScopeLevel::State => $query->where('state_id', $context->stateId()),
            ScopeLevel::Chapter => $query->where('chapter_id', $context->chapterId()),
            default => $query->whereRaw('1 = 0'),
        };
    }
}
