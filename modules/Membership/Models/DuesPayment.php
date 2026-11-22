<?php

namespace Modules\Membership\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Models\GatewayTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Membership\Enums\PaymentMethod;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Money received for one or more of a school's dues, with its receipt.
 *
 * @property PaymentMethod $method
 */
#[Fillable([
    'receipt_number', 'school_id', 'amount_kobo', 'method', 'gateway_transaction_id', 'reference',
    'paid_at', 'paid_by', 'recorded_by', 'notes',
])]
class DuesPayment extends Model
{
    use BelongsToSchool, LogsActivity;

    protected function casts(): array
    {
        return [
            'amount_kobo' => 'integer',
            'method' => PaymentMethod::class,
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<MembershipDue, $this>
     */
    public function dues(): HasMany
    {
        return $this->hasMany(MembershipDue::class, 'dues_payment_id');
    }

    /**
     * @return HasMany<DuesAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(DuesAllocation::class);
    }

    /**
     * @return BelongsTo<GatewayTransaction, $this>
     */
    public function gatewayTransaction(): BelongsTo
    {
        return $this->belongsTo(GatewayTransaction::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('dues')->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
