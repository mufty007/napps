<?php

namespace Modules\Claims\Models;

use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClaimSettlement extends Model
{
    protected $fillable = ['claim_case_id', 'amount_kobo', 'terms', 'paid_at', 'recorded_by'];

    protected function casts(): array
    {
        return ['amount_kobo' => 'integer', 'paid_at' => 'datetime'];
    }

    /** @return BelongsTo<ClaimCase, $this> */
    public function claimCase(): BelongsTo
    {
        return $this->belongsTo(ClaimCase::class);
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
