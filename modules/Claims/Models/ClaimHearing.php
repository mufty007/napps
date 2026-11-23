<?php

namespace Modules\Claims\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClaimHearing extends Model
{
    protected $fillable = ['claim_case_id', 'scheduled_at', 'venue', 'outcome', 'created_by'];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime'];
    }

    /** @return BelongsTo<ClaimCase, $this> */
    public function claimCase(): BelongsTo
    {
        return $this->belongsTo(ClaimCase::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
