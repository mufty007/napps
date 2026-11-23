<?php

namespace Modules\Claims\Models;

use App\Models\State;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-state switch for debt claims. A state only goes live once a barrister has
 * confirmed the legal arrangements there (CLM-2).
 */
class StateClaimSetting extends Model
{
    protected $fillable = ['state_id', 'enabled', 'barrister_confirmed_at', 'notes', 'updated_by'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'barrister_confirmed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<State, $this> */
    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
