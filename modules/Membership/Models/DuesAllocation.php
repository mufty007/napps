<?php

namespace Modules\Membership\Models;

use App\Enums\ScopeLevel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Split ledger: the part of a dues payment that belongs to one level
 * (national, a state or a chapter). Written once with the payment.
 *
 * @property ScopeLevel $level
 */
#[Fillable(['dues_payment_id', 'membership_due_id', 'level', 'state_id', 'chapter_id', 'amount_kobo'])]
class DuesAllocation extends Model
{
    protected function casts(): array
    {
        return [
            'level' => ScopeLevel::class,
            'amount_kobo' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<DuesPayment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(DuesPayment::class, 'dues_payment_id');
    }
}
