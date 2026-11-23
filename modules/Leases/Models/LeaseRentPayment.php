<?php

namespace Modules\Leases\Models;

use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One rent payment on a lease. Reached through its lease, which carries the
 * school scope.
 *
 * @property Carbon $paid_at
 */
class LeaseRentPayment extends Model
{
    protected $fillable = ['lease_id', 'amount_kobo', 'paid_at', 'method', 'recorded_by', 'notes'];

    protected function casts(): array
    {
        return ['amount_kobo' => 'integer', 'paid_at' => 'date'];
    }

    /** @return BelongsTo<Lease, $this> */
    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function amount(): string
    {
        return Money::format($this->amount_kobo);
    }
}
