<?php

namespace Modules\Community\Notifications;

use Illuminate\Notifications\Notification;
use Modules\Community\Models\Thread;
use Modules\Community\Models\ThreadReply;
use Modules\Community\Notifications\Concerns\UsesPreferences;

/**
 * COM-2: "I get notified of answers" (followers of a thread), and the
 * author of an answer hears when it is marked best.
 */
class ThreadActivity extends Notification
{
    use UsesPreferences;

    public const CATEGORY = 'community.activity';

    public function __construct(
        public readonly Thread $thread,
        public readonly ThreadReply $reply,
        public readonly bool $bestAnswer = false,
    ) {}

    /**
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->bestAnswer
                ? __('Your answer was marked best on ":title"', ['title' => str($this->thread->title)->limit(60)])
                : __(':name answered ":title"', ['name' => $this->reply->author->name ?? __('Someone'), 'title' => str($this->thread->title)->limit(60)]),
            'body' => str($this->reply->body)->limit(120)->toString(),
            'url' => route('community.forums.threads.show', [$this->thread->forum_id, $this->thread]).'#reply-'.$this->reply->id,
            'icon' => $this->bestAnswer ? 'award' : 'messages-square',
        ];
    }
}
