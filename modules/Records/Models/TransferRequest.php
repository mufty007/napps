<?php

namespace Modules\Records\Models;

use App\Models\School;
use App\Models\Scopes\SchoolOwnedScope;
use App\Models\User;
use App\Support\Money;
use App\Support\Scope\ActiveContext;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Records\Enums\TransferStatus;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A request for transfer clearance. `school_id` is the school that holds the
 * pupil's record and must answer within five working days; `to_school_id` is
 * the school asking. Both can see it, which is why this model does not use the
 * single-column BelongsToSchool scope: see scopeVisibleTo().
 *
 * @property TransferStatus $status
 */
#[Fillable([
    'school_id', 'to_school_id', 'student_id', 'requested_by', 'status', 'sla_due_at',
    'response_notes', 'responded_by', 'responded_at', 'outstanding_kobo',
])]
class TransferRequest extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'status' => TransferStatus::class,
            'sla_due_at' => 'datetime',
            'responded_at' => 'datetime',
            'outstanding_kobo' => 'integer',
        ];
    }

    /** The school that holds the record and answers. @return BelongsTo<School, $this> */
    public function fromSchool(): BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id')->withoutGlobalScopes();
    }

    /** The school the pupil is moving to. @return BelongsTo<School, $this> */
    public function toSchool(): BelongsTo
    {
        return $this->belongsTo(School::class, 'to_school_id')->withoutGlobalScopes();
    }

    /**
     * The asking school may not browse the other school's register, so the
     * student scope is lifted here; views only show what the policy allows.
     *
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withoutGlobalScope(SchoolOwnedScope::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function responder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }

    public function isPending(): bool
    {
        return $this->status === TransferStatus::Pending;
    }

    /** Pending past its five-working-day deadline. */
    public function isOverdue(): bool
    {
        return $this->isPending() && $this->sla_due_at->isPast();
    }

    public function outstanding(): string
    {
        return Money::format($this->outstanding_kobo);
    }

    /** @param  Builder<self>  $query */
    public function scopePending(Builder $query): void
    {
        $query->where('status', TransferStatus::Pending->value);
    }

    /** @param  Builder<self>  $query */
    public function scopeOverdue(Builder $query): void
    {
        $query->where('status', TransferStatus::Pending->value)->where('sla_due_at', '<', now());
    }

    /**
     * Requests the active role may see: those its schools must answer and
     * those they asked for.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, ActiveContext $context): void
    {
        $query->where(function (Builder $q) use ($context) {
            $q->where(fn (Builder $w) => $context->constrainBySchoolColumn($w, 'school_id'))
                ->orWhere(fn (Builder $w) => $context->constrainBySchoolColumn($w, 'to_school_id'));
        });
    }

    /**
     * Requests the active role's school(s) hold the record for (and answer).
     *
     * @param  Builder<self>  $query
     */
    public function scopeHeldBy(Builder $query, ActiveContext $context): void
    {
        $context->constrainBySchoolColumn($query, 'school_id');
    }

    /**
     * Requests the active role's school(s) asked for.
     *
     * @param  Builder<self>  $query
     */
    public function scopeAskedBy(Builder $query, ActiveContext $context): void
    {
        $context->constrainBySchoolColumn($query, 'to_school_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('records')->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
