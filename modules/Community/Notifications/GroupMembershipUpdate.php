<?php

namespace Modules\Community\Notifications;

use App\Models\User;
use Illuminate\Notifications\Notification;
use Modules\Community\Models\Space;
use Modules\Community\Notifications\Concerns\UsesPreferences;

/**
 * Group admins hear about join requests; members hear about invitations
 * and approvals.
 */
class GroupMembershipUpdate extends Notification
{
    use UsesPreferences;

    public const CATEGORY = 'community.groups';

    /**
     * @param  'requested'|'invited'|'approved'  $event
     */
    public function __construct(
        public readonly Space $group,
        public readonly string $event,
        public readonly ?User $actor = null,
    ) {}

    /**
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        $replace = ['name' => $this->actor->name ?? __('Someone'), 'group' => $this->group->name];

        return [
            'title' => match ($this->event) {
                'requested' => __(':name asked to join :group', $replace),
                'invited' => __(':name invited you to :group', $replace),
                default => __('You are now a member of :group', $replace),
            },
            'body' => (string) $this->group->description,
            'url' => route('community.groups.show', $this->group).($this->event === 'requested' ? '?tab=members' : ''),
            'icon' => 'users',
        ];
    }
}
