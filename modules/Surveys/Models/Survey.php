<?php

namespace Modules\Surveys\Models;

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

class Survey extends Model
{
    public const DRAFT = 'draft';

    public const OPEN = 'open';

    public const CLOSED = 'closed';

    protected $fillable = [
        'title', 'description', 'status', 'created_by', 'scope_level', 'scope_id',
        'opened_at', 'closes_at',
    ];

    protected function casts(): array
    {
        return [
            'scope_level' => ScopeLevel::class,
            'scope_id' => 'integer',
            'opened_at' => 'datetime',
            'closes_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<SurveyQuestion, $this> */
    public function questions(): HasMany
    {
        return $this->hasMany(SurveyQuestion::class)->orderBy('sort')->orderBy('id');
    }

    /** @return HasMany<SurveyResponse, $this> */
    public function responses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class);
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

    public function isClosed(): bool
    {
        return $this->status === self::CLOSED
            || ($this->status === self::OPEN && $this->closes_at !== null && $this->closes_at->isPast());
    }

    public function statusLabel(): string
    {
        return match (true) {
            $this->isDraft() => __('Draft'),
            $this->isOpen() => __('Open'),
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

    /** Who the survey is sent to, for display. */
    public function audienceLabel(): string
    {
        return match ($this->scope_level) {
            ScopeLevel::State => (State::query()->whereKey($this->scope_id)->value('name') ?? __('State')).' '.__('State'),
            ScopeLevel::Chapter => Chapter::query()->whereKey($this->scope_id)->value('name') ?? __('Chapter'),
            default => __('All member schools'),
        };
    }

    /** Whether a school falls inside the survey's audience. */
    public function targets(School $school): bool
    {
        return match ($this->scope_level) {
            ScopeLevel::State => $school->state_id === $this->scope_id,
            ScopeLevel::Chapter => $school->chapter_id === $this->scope_id,
            default => true,
        };
    }

    /**
     * Member schools the survey is addressed to, narrowed by the viewer's own
     * scope (an executive sees only their slice of the audience).
     *
     * @return Builder<School>
     */
    public function eligibleSchools(): Builder
    {
        return School::query()
            ->whereIn('membership_status', [MembershipStatus::Active->value, MembershipStatus::Owing->value])
            ->when($this->scope_level === ScopeLevel::State, fn (Builder $q) => $q->where('state_id', $this->scope_id))
            ->when($this->scope_level === ScopeLevel::Chapter, fn (Builder $q) => $q->where('chapter_id', $this->scope_id));
    }

    /** @param  Builder<self>  $query */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', self::OPEN)
            ->where(fn (Builder $q) => $q->whereNull('closes_at')->orWhere('closes_at', '>', now()));
    }

    /**
     * Open surveys addressed to this school.
     *
     * @param  Builder<self>  $query
     */
    public function scopeOpenFor(Builder $query, School $school): void
    {
        $query->open()->where(function (Builder $q) use ($school) {
            $q->where('scope_level', ScopeLevel::National->value)
                ->orWhere(fn (Builder $s) => $s->where('scope_level', ScopeLevel::State->value)->where('scope_id', $school->state_id))
                ->orWhere(fn (Builder $c) => $c->where('scope_level', ScopeLevel::Chapter->value)->where('scope_id', $school->chapter_id));
        });
    }

    /**
     * Surveys an executive may see in their list: the ones they run, plus
     * national surveys that are out of draft (their slice of the results).
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, ActiveContext $context): void
    {
        $query->where(function (Builder $q) use ($context) {
            $q->where(fn (Builder $m) => $m->manageableBy($context))
                ->orWhere(fn (Builder $n) => $n->where('scope_level', ScopeLevel::National->value)->where('status', '!=', self::DRAFT));
        });
    }

    /**
     * Surveys the active role runs: national roles run everything; state and
     * chapter executives run surveys aimed at their own place in the chain
     * (plus any they created themselves).
     *
     * @param  Builder<self>  $query
     */
    public function scopeManageableBy(Builder $query, ActiveContext $context): void
    {
        $level = $context->level();

        if ($level === ScopeLevel::National) {
            return;
        }

        $query->where(function (Builder $q) use ($context, $level) {
            $q->where('created_by', $context->user()?->getKey() ?? 0);

            if ($level === ScopeLevel::State && $context->stateId()) {
                $q->orWhere(fn (Builder $s) => $s->where('scope_level', ScopeLevel::State->value)->where('scope_id', $context->stateId()))
                    ->orWhere(fn (Builder $c) => $c->where('scope_level', ScopeLevel::Chapter->value)
                        ->whereIn('scope_id', Chapter::query()->select('id')->where('state_id', $context->stateId())));
            }

            if ($level === ScopeLevel::Chapter && $context->chapterId()) {
                $q->orWhere(fn (Builder $c) => $c->where('scope_level', ScopeLevel::Chapter->value)->where('scope_id', $context->chapterId()));
            }
        });
    }

    public function isManageableBy(ActiveContext $context): bool
    {
        return static::query()->whereKey($this->getKey())->manageableBy($context)->exists();
    }
}
