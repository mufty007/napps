<?php

namespace Modules\Membership\Models;

use App\Models\Chapter;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Links a parent account to the chapter that enrolled them.
 * Parents cannot self-register; chapter executives add them.
 */
class ChapterParent extends Model
{
    protected $fillable = ['chapter_id', 'user_id', 'added_by', 'notes'];

    /** @return BelongsTo<Chapter, $this> */
    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
