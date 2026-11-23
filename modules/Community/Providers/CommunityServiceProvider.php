<?php

namespace Modules\Community\Providers;

use App\Enums\Role;
use App\Models\User;
use App\Support\Modules\ModuleServiceProvider;
use App\Support\Navigation\Navigation;
use App\Support\Navigation\NavItem;
use App\Support\Notifications\NotificationPreferences;
use Illuminate\Support\Facades\Broadcast;
use Modules\Community\Models\Conversation;
use Modules\Community\Services\SpaceDirectory;

/**
 * Members-only feed, forums, groups, chat, polls and formal votes,
 * profiles, moderation. Never reads fees/student tables. Stories: COM-1..5.
 */
class CommunityServiceProvider extends ModuleServiceProvider
{
    protected string $module = 'Community';

    public function register(): void
    {
        // One membership profile per person per request.
        $this->app->scoped(SpaceDirectory::class);
    }

    protected function navigation(Navigation $navigation): void
    {
        $roles = array_values(array_filter(Role::cases(), fn (Role $r) => $r->hasCommunityAccess()));

        // Home is the feed (like Facebook / LinkedIn); every community screen is
        // its own sidebar page rather than a tab.
        $pages = [
            ['home', 'Home', 'community.feed', 'house', 0, 'community.feed|community.index|community.posts.*', 'community.access'],
            ['community.forums', 'Forums', 'community.forums.index', 'messages-square', 91, 'community.forums.*', 'community.access'],
            ['community.groups', 'Groups', 'community.groups.index', 'users', 92, 'community.groups.*', 'community.access'],
            ['community.chat', 'Chat', 'community.chat.index', 'message-circle', 93, 'community.chat.*', 'community.access'],
            ['community.notifications', 'Notifications', 'notifications.index', 'bell', 94, 'notifications.*', 'community.access'],
            ['community.moderation', 'Reports', 'community.moderation.index', 'flag', 95, 'community.moderation.*', 'community.moderate'],
        ];

        foreach ($pages as [$key, $label, $route, $icon, $order, $active, $permission]) {
            $navigation->add(new NavItem(
                key: $key,
                label: $label,
                route: $route,
                icon: $icon,
                roles: $roles,
                order: $order,
                active: $active,
                permission: $permission,
            ));
        }
    }

    protected function bootModule(): void
    {
        $this->app->make(NotificationPreferences::class)
            ->register('community.activity', 'Replies and answers', 'Comments on your posts, replies to your comments, answers in threads you follow.', order: 20)
            ->register('community.messages', 'Chat messages', 'New messages while you are away (one notice per chat).', order: 30)
            ->register('community.groups', 'Groups', 'Requests to join your groups, invitations and approvals.', order: 40);

        // Private chats: members only. Executives cannot listen in.
        Broadcast::channel('community.conversation.{id}', fn (User $user, int $id) => Conversation::query()
            ->whereKey($id)
            ->whereHas('members', fn ($q) => $q->where('users.id', $user->id))
            ->exists());
    }
}
