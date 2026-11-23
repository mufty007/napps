<?php

namespace Modules\Community\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A member's report of a post, comment, forum thread / reply or message.
 * It goes to the moderators of the space it sits in (COM-5); every
 * decision is audit-logged.
 */
#[Fillable(['reporter_id', 'reportable_type', 'reportable_id', 'space_id', 'reason', 'details', 'status', 'action', 'handled_by', 'handled_at'])]
class Report extends Model
{
    use LogsActivity;

    public const REASONS = [
        'spam' => 'Spam or advertising',
        'abuse' => 'Abuse or harassment',
        'false' => 'False or misleading',
        'private' => 'Shares private information',
        'other' => 'Something else',
    ];

    protected $table = 'community_reports';

    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /**
     * @return BelongsTo<Space, $this>
     */
    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }

    public function reasonLabel(): string
    {
        return __(self::REASONS[$this->reason] ?? 'Something else');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('moderation')->logOnly(['status', 'action', 'handled_by'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
