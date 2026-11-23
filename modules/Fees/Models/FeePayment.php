<?php

namespace Modules\Fees\Models;

use App\Models\GatewayTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Fees\Enums\FeePaymentMethod;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Money received against an invoice. Reached through the invoice, which is
 * school-scoped. payment_intent_id is the gateway transaction and is unique,
 * so an online payment can never be credited twice.
 *
 * @property FeePaymentMethod $method
 * @property array<string, mixed>|null $meta
 */
class FeePayment extends Model
{
    use LogsActivity;

    protected $fillable = [
        'fee_invoice_id', 'amount_kobo', 'method', 'reference', 'recorded_by', 'payment_intent_id', 'paid_at', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'amount_kobo' => 'integer',
            'method' => FeePaymentMethod::class,
            'paid_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<FeeInvoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(FeeInvoice::class, 'fee_invoice_id')->withoutGlobalScopes();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @return BelongsTo<GatewayTransaction, $this>
     */
    public function intent(): BelongsTo
    {
        return $this->belongsTo(GatewayTransaction::class, 'payment_intent_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('fees')
            ->logOnly(['fee_invoice_id', 'amount_kobo', 'method', 'reference', 'recorded_by', 'paid_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
