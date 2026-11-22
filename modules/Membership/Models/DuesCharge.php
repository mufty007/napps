<?php

namespace Modules\Membership\Models;

use App\Enums\MembershipStatus;
use App\Enums\ScopeLevel;
use App\Models\Chapter;
use App\Models\School;
use App\Models\State;
use App\Models\User;
use App\Support\Scope\ActiveContext;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Membership\Enums\DuesKind;
use Modules\Membership\Policies\DuesChargePolicy;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Yearly dues or a levy set by national, a state or a chapter for every
 * member school under it. Editable until issued; issuing bills each school.
 *
 * @property ScopeLevel $scope_type
 * @property DuesKind $kind
 */
#[UsePolicy(DuesChargePolicy::class)]
#[Fillable([
    'scope_type', 'scope_id', 'kind', 'title', 'description', 'year', 'amount_kobo', 'due_date',
    'share_national', 'share_state', 'share_chapter', 'created_by', 'issued_at', 'issued_by', 'cancelled_at',
])]
class DuesCharge extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'scope_type' => ScopeLevel::class,
            'scope_id' => 'integer',
            'kind' => DuesKind::class,
            'year' => 'integer',
            'amount_kobo' => 'integer',
            'due_date' => 'date',
            'share_national' => 'integer',
            'share_state' => 'integer',
            'share_chapter' => 'integer',
            'issued_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<MembershipDue, $this>
     */
    public function dues(): HasMany
    {
        return $this->hasMany(MembershipDue::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Charges that apply anywhere in the active role's area: national ones,
     * its state's and its chapters'. Personal and school roles see none here.
     *
     * @param  Builder<DuesCharge>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, ActiveContext $context): void
    {
        $level = $context->level();

        match ($level) {
            ScopeLevel::National => null,
            ScopeLevel::State => $query->where(fn (Builder $q) => $q
                ->where('scope_type', ScopeLevel::National)
                ->orWhere(fn (Builder $q) => $q->where('scope_type', ScopeLevel::State)->where('scope_id', $context->stateId()))
                ->orWhere(fn (Builder $q) => $q->where('scope_type', ScopeLevel::Chapter)
                    ->whereIn('scope_id', Chapter::query()->select('id')->where('state_id', $context->stateId())))),
            ScopeLevel::Chapter => $query->where(fn (Builder $q) => $q
                ->where('scope_type', ScopeLevel::National)
                ->orWhere(fn (Builder $q) => $q->where('scope_type', ScopeLevel::State)->where('scope_id', $context->stateId()))
                ->orWhere(fn (Builder $q) => $q->where('scope_type', ScopeLevel::Chapter)->where('scope_id', $context->chapterId()))),
            default => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * Charges that bill a given school (national + its state + its chapter).
     *
     * @param  Builder<DuesCharge>  $query
     */
    #[Scope]
    protected function applicableTo(Builder $query, School $school): void
    {
        $query->where(fn (Builder $q) => $q
            ->where('scope_type', ScopeLevel::National)
            ->orWhere(fn (Builder $q) => $q->where('scope_type', ScopeLevel::State)->where('scope_id', $school->state_id))
            ->orWhere(fn (Builder $q) => $q->where('scope_type', ScopeLevel::Chapter)->where('scope_id', $school->chapter_id)));
    }

    /**
     * @param  Builder<DuesCharge>  $query
     */
    #[Scope]
    protected function live(Builder $query): void
    {
        $query->whereNotNull('issued_at')->whereNull('cancelled_at');
    }

    /**
     * Approved member schools this charge bills (never pending applicants).
     *
     * @return Builder<School>
     */
    public function schoolsQuery(): Builder
    {
        $query = School::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->whereIn('membership_status', MembershipStatus::members());

        return match ($this->scope_type) {
            ScopeLevel::State => $query->where('state_id', $this->scope_id),
            ScopeLevel::Chapter => $query->where('chapter_id', $this->scope_id),
            default => $query,
        };
    }

    /**
     * "National", "Kano State" or "Kano Chapter".
     */
    public function placeName(): string
    {
        return match ($this->scope_type) {
            ScopeLevel::State => __(':name State', ['name' => State::query()->whereKey($this->scope_id)->value('name')]),
            ScopeLevel::Chapter => (string) Chapter::query()->whereKey($this->scope_id)->value('name'),
            default => __('National'),
        };
    }

    public function isIssued(): bool
    {
        return $this->issued_at !== null;
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    /**
     * Split of each payment between levels, as percentages summing to 100.
     *
     * @return array<string, int> level value => percent
     */
    public function shares(): array
    {
        return array_filter([
            ScopeLevel::National->value => $this->share_national,
            ScopeLevel::State->value => $this->share_state,
            ScopeLevel::Chapter->value => $this->share_chapter,
        ]);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('dues')->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
