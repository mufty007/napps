<?php

namespace Modules\Announcements\Models;

use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A school's single ballot in an election. Who a school voted for is never
 * shown to anyone; only the closed tally is.
 */
class ElectionVote extends Model
{
    public $timestamps = false;

    protected $fillable = ['election_id', 'candidate_id', 'school_id', 'cast_by', 'cast_at'];

    protected function casts(): array
    {
        return ['cast_at' => 'datetime'];
    }

    /** @return BelongsTo<Election, $this> */
    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    /** @return BelongsTo<ElectionCandidate, $this> */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(ElectionCandidate::class, 'candidate_id');
    }

    /** @return BelongsTo<School, $this> */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class)->withoutGlobalScopes();
    }

    /** @return BelongsTo<User, $this> */
    public function castBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cast_by');
    }
}
