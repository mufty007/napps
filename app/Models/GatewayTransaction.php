<?php

namespace App\Models;

use App\Enums\GatewayStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One online payment attempt at the gateway, matched by its reference
 * (PRD: every payment can be matched to a gateway reference).
 *
 * "purpose" names the module flow that started it (e.g. "membership.dues");
 * that module listens for App\Events\PaymentConfirmed and records the money
 * in its own tables. Not org-scoped: it is only ever looked up by reference.
 *
 * @property GatewayStatus $status
 * @property array<string, mixed>|null $metadata
 */
#[Fillable([
    'reference', 'gateway', 'purpose', 'school_id', 'payer_id', 'amount_kobo', 'currency', 'status',
    'channel', 'gateway_response', 'authorization_url', 'metadata', 'gateway_payload', 'paid_at', 'verified_at',
])]
class GatewayTransaction extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'amount_kobo' => 'integer',
            'status' => GatewayStatus::class,
            'metadata' => 'array',
            'gateway_payload' => 'array',
            'paid_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payer_id');
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class)->withoutGlobalScopes();
    }

    public function meta(string $key, mixed $default = null): mixed
    {
        return data_get($this->metadata, $key, $default);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('payments')
            ->logOnly(['reference', 'purpose', 'amount_kobo', 'status', 'channel', 'paid_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
