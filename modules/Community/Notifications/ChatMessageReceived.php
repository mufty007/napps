<?php

namespace Modules\Community\Notifications;

use Illuminate\Notifications\Notification;
use Modules\Community\Models\Conversation;
use Modules\Community\Models\Message;
use Modules\Community\Notifications\Concerns\UsesPreferences;

/**
 * A new chat message while you were away. ChatController sends at most one
 * unread notice per conversation, so a busy chat does not flood the bell.
 */
class ChatMessageReceived extends Notification
{
    use UsesPreferences;

    public const CATEGORY = 'community.messages';

    public function __construct(
        public readonly Conversation $conversation,
        public readonly Message $message,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $sender = $this->message->sender->name ?? __('Someone');

        return [
            'title' => $this->conversation->type === 'direct'
                ? __(':name sent you a message', ['name' => $sender])
                : __('New messages in :chat', ['chat' => $this->conversation->title ?: __('a group chat')]),
            'body' => $this->message->preview(120),
            'url' => route('community.chat.show', $this->conversation),
            'icon' => 'message-circle',
            'conversation_id' => $this->conversation->id,
        ];
    }
}
