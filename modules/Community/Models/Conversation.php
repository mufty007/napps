<?php

namespace Modules\Community\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $table = 'community_conversations';

    protected $fillable = ['type', 'title', 'space_id', 'created_by'];

    /** @return BelongsToMany<User, $this> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'community_conversation_members')
            ->withPivot(['last_read_at', 'last_read_message_id'])
            ->withTimestamps();
    }

    /** @return HasMany<Message, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /** @return BelongsTo<Space, $this> */
    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function displayTitle(User $viewer): string
    {
        if (filled($this->title)) {
            return $this->title;
        }

        return $this->members
            ->reject(fn (User $u) => $u->is($viewer))
            ->pluck('name')
            ->join(', ') ?: __('Conversation');
    }
}
