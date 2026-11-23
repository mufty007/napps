<?php

namespace Modules\Fees\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Fees\Enums\ReminderKind;

/**
 * A reminder sent for an invoice. One row per kind keeps the nightly job from
 * sending the same reminder twice.
 *
 * @property ReminderKind $kind
 */
class FeeReminder extends Model
{
    protected $fillable = ['fee_invoice_id', 'channel', 'sent_at', 'kind'];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'kind' => ReminderKind::class,
        ];
    }

    /**
     * @return BelongsTo<FeeInvoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(FeeInvoice::class, 'fee_invoice_id')->withoutGlobalScopes();
    }
}
