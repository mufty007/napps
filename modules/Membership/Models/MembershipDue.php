<?php

namespace Modules\Membership\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Membership\Enums\DueStatus;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * What one school owes for one issued charge.
 *
 * @property DueStatus $status
 */
#[Fillable(['school_id', 'dues_charge_id', 'amount_kobo', 'due_date', 'status', 'dues_payment_id', 'paid_at'])]
class MembershipDue extends Model
{
    use BelongsToSchool, LogsActivity;

    protected function casts(): array
    {
        return [
            'amount_kobo' => 'integer',
            'due_date' => 'date',
            'status' => DueStatus::class,
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<DuesCharge, $this>
     */
    public function charge(): BelongsTo
    {
        return $this->belongsTo(DuesCharge::class, 'dues_charge_id');
    }

    /**
     * @return BelongsTo<DuesPayment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(DuesPayment::class, 'dues_payment_id');
    }

    /**
     * @param  Builder<MembershipDue>  $query
     */
    #[Scope]
    protected function unpaid(Builder $query): void
    {
        $query->where('status', DueStatus::Unpaid);
    }

    /**
     * Unpaid and past its due date: what makes a school "Owing".
     *
     * @param  Builder<MembershipDue>  $query
     */
    #[Scope]
    protected function overdue(Builder $query): void
    {
        $query->where('status', DueStatus::Unpaid)->where('due_date', '<=', today()->toDateString());
    }

    public function isOverdue(): bool
    {
        return $this->status === DueStatus::Unpaid && $this->due_date->lte(today());
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('dues')
            ->logOnly(['school_id', 'dues_charge_id', 'amount_kobo', 'status', 'dues_payment_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
