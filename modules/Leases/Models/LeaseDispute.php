<?php

namespace Modules\Leases\Models;

use App\Enums\Role;
use App\Models\School;
use App\Models\User;
use App\Support\Scope\ActiveContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Leases\Enums\DisputeStatus;

/**
 * A dispute with a landlord. It carries school_id but is not school-scoped by
 * a global scope: an assigned panel lawyer must reach it too, so visibility
 * is decided in scopeVisibleTo().
 *
 * @property DisputeStatus $status
 * @property ?Carbon $resolved_at
 */
class LeaseDispute extends Model
{
    protected $fillable = [
        'lease_id', 'school_id', 'opened_by', 'summary', 'status',
        'assigned_lawyer_id', 'resolved_at', 'resolution_notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => DisputeStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Lease, $this> */
    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class)->withoutGlobalScopes();
    }

    /** @return BelongsTo<School, $this> */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class)->withoutGlobalScopes();
    }

    /** @return BelongsTo<User, $this> */
    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    /** @return BelongsTo<User, $this> */
    public function lawyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_lawyer_id');
    }

    /** @param  Builder<self>  $query */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', '!=', DisputeStatus::Resolved->value);
    }

    /** @param  Builder<self>  $query */
    public function scopeResolved(Builder $query): void
    {
        $query->where('status', DisputeStatus::Resolved->value);
    }

    /**
     * What the active role may see: the secretariat and national executives
     * everything, panel lawyers only disputes assigned to them, everyone else
     * the disputes of schools inside their State, Chapter or School.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, ActiveContext $context): void
    {
        if ($context->isSystem() || $context->is(Role::Secretariat, Role::NationalExecutive)) {
            return;
        }

        if ($context->is(Role::LegalPanel)) {
            $query->where('assigned_lawyer_id', $context->user()?->getKey() ?? 0);

            return;
        }

        $context->constrainBySchoolColumn($query);
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }
}
