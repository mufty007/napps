<?php

namespace Modules\Claims\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Claims\Enums\ClaimEventType;

/**
 * One row of a claim's timeline. Append-only: there is no updated_at.
 *
 * @property ClaimEventType $type
 * @property array<string, mixed>|null $meta
 */
class ClaimEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['claim_case_id', 'user_id', 'type', 'body', 'meta', 'created_at'];

    protected function casts(): array
    {
        return [
            'type' => ClaimEventType::class,
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ClaimCase, $this> */
    public function claimCase(): BelongsTo
    {
        return $this->belongsTo(ClaimCase::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
