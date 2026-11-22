<?php

namespace Modules\Membership\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A school is "Suspended" while it has a suspension that has not been lifted.
 * Automatic ones (overdue-dues rule) lift themselves once dues are paid;
 * manual ones need an executive to reinstate the school.
 */
#[Fillable(['school_id', 'reason', 'automatic', 'suspended_by', 'suspended_at', 'lifted_by', 'lifted_at', 'lift_note'])]
class MembershipSuspension extends Model
{
    use BelongsToSchool, LogsActivity;

    protected function casts(): array
    {
        return [
            'automatic' => 'boolean',
            'suspended_at' => 'datetime',
            'lifted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function suspender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suspended_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function lifter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lifted_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('membership')->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
