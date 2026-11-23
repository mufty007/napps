<?php

namespace Modules\Announcements\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventRsvp extends Model
{
    public const STATUSES = ['going', 'maybe', 'declined'];

    protected $fillable = ['event_id', 'user_id', 'status'];

    /** @return array<string, string> */
    public static function statusLabels(): array
    {
        return [
            'going' => __('Going'),
            'maybe' => __('Maybe'),
            'declined' => __("Can't go"),
        ];
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? $this->status;
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
