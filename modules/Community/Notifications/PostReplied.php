<?php

namespace Modules\Community\Notifications;

use Illuminate\Notifications\Notification;
use Modules\Community\Models\Comment;
use Modules\Community\Models\Post;
use Modules\Community\Notifications\Concerns\UsesPreferences;

/**
 * Someone commented on your post, or replied to your comment.
 */
class PostReplied extends Notification
{
    use UsesPreferences;

    public const CATEGORY = 'community.activity';

    public function __construct(
        public readonly Post $post,
        public readonly Comment $comment,
        public readonly bool $toCommentAuthor = false,
    ) {}

    /**
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        $name = $this->comment->author->name ?? __('Someone');

        return [
            'title' => $this->toCommentAuthor
                ? __(':name replied to your comment', ['name' => $name])
                : __(':name commented on your post', ['name' => $name]),
            'body' => str($this->comment->body)->limit(120)->toString(),
            'url' => route('community.posts.show', $this->post).'#comment-'.$this->comment->id,
            'icon' => 'message-circle',
        ];
    }
}
