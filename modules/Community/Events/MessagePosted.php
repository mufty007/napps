<?php

namespace Modules\Community\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Modules\Community\Models\Message;

/**
 * Tells open chat windows a message arrived (Reverb). Carries only ids:
 * clients fetch the rendered message over HTTP, which re-checks access.
 * Without Reverb the chat page polls, so nothing depends on this.
 */
class MessagePosted implements ShouldBroadcastNow
{
    use InteractsWithSockets;

    public function __construct(public readonly Message $message) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('community.conversation.'.$this->message->conversation_id);
    }

    public function broadcastAs(): string
    {
        return 'message.posted';
    }

    /**
     * @return array<string, int>
     */
    public function broadcastWith(): array
    {
        return ['id' => $this->message->id];
    }
}
