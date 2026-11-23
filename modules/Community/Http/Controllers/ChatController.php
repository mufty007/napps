<?php

namespace Modules\Community\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Notifications\NotificationPreferences;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Modules\Community\Events\MessagePosted;
use Modules\Community\Models\Conversation;
use Modules\Community\Models\Message;
use Modules\Community\Models\Space;
use Modules\Community\Notifications\ChatMessageReceived;
use Modules\Community\Services\SpaceDirectory;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * COM-3: one-to-one chats and channels for each chapter and committee.
 * Executives cannot read private chats: access is membership only.
 * Live updates come over Reverb when it is configured; otherwise the page
 * polls messages(), so chat keeps working on a plain server and on 3G.
 */
class ChatController extends Controller
{
    public function index(Request $request, SpaceDirectory $directory): View
    {
        $user = $request->user();

        $conversations = Conversation::query()
            ->whereHas('members', fn ($q) => $q->where('users.id', $user->id))
            ->with(['members', 'space', 'messages' => fn ($q) => $q->with('sender')->latest('id')->limit(1)])
            ->latest('updated_at')
            ->get();

        return view('community::chat.index', [
            'conversations' => $conversations,
            'unread' => $this->unreadCounts($user, $conversations),
            'channels' => $directory->forViewer()->filter(fn (Space $s) => in_array($s->type, ['chapter', 'committee'], true))->values(),
            'search' => trim((string) $request->query('find')),
            'people' => $directory->searchMembers((string) $request->query('find'), $user),
        ]);
    }

    public function show(Request $request, Conversation $conversation, NotificationPreferences $preferences): View
    {
        $user = $request->user();
        $this->authorizeMember($conversation, $user);

        $before = $request->integer('before');
        $messages = $conversation->messages()
            ->with(['sender', 'media'])
            ->when($before, fn ($q) => $q->where('id', '<', $before))
            ->latest('id')
            ->limit(50)
            ->get()
            ->reverse()
            ->values();

        $this->markRead($conversation, $user, (int) $conversation->messages()->max('id'));

        return view('community::chat.show', [
            'conversation' => $conversation->load('members', 'space'),
            'messages' => $messages,
            'hasEarlier' => $messages->isNotEmpty() && $conversation->messages()->where('id', '<', $messages->first()->id)->exists(),
            'lastId' => (int) $conversation->messages()->max('id'),
            'seenUpTo' => $this->seenUpTo($conversation, $user, $preferences),
            'realtime' => in_array(config('broadcasting.default'), ['reverb', 'pusher'], true),
            'showTyping' => $preferences->setting($user, 'typing', true),
        ]);
    }

    /**
     * New messages since ?after=ID, rendered, for polling and Reverb pings.
     */
    public function messages(Request $request, Conversation $conversation, NotificationPreferences $preferences): JsonResponse
    {
        $user = $request->user();
        $this->authorizeMember($conversation, $user);

        $messages = $conversation->messages()
            ->with(['sender', 'media'])
            ->where('id', '>', $request->integer('after'))
            ->orderBy('id')
            ->limit(100)
            ->get();

        if ($messages->isNotEmpty()) {
            $this->markRead($conversation, $user, (int) $messages->last()->id);
        }

        return response()->json([
            'html' => $messages->map(fn (Message $m) => view('community::chat.partials.message', ['message' => $m, 'conversation' => $conversation])->render())->implode(''),
            'last_id' => $messages->isNotEmpty() ? $messages->last()->id : $request->integer('after'),
            'seen_up_to' => $this->seenUpTo($conversation, $user, $preferences),
        ]);
    }

    public function storeMessage(Request $request, Conversation $conversation): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $this->authorizeMember($conversation, $user);

        $validated = $request->validate([
            'body' => ['nullable', 'string', 'max:4000', 'required_without:photo'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.Message::PHOTO_MAX_KB],
        ]);

        $message = DB::transaction(function () use ($request, $conversation, $user, $validated) {
            $message = Message::query()->create([
                'conversation_id' => $conversation->id,
                'sender_id' => $user->id,
                'body' => trim((string) ($validated['body'] ?? '')),
            ]);

            if ($request->hasFile('photo')) {
                $message->addMediaFromRequest('photo')
                    ->usingFileName(Str::random(32).'.'.$request->file('photo')->extension())
                    ->toMediaCollection('photo');
            }

            return $message;
        });
        $message->setRelation('sender', $user)->load('media');

        $conversation->touch();
        $this->markRead($conversation, $user, $message->id);
        $this->notifyOthers($conversation, $message);

        rescue(fn () => broadcast(new MessagePosted($message))->toOthers(), report: false);

        if ($request->expectsJson()) {
            return response()->json([
                'id' => $message->id,
                'html' => view('community::chat.partials.message', ['message' => $message, 'conversation' => $conversation])->render(),
            ]);
        }

        return redirect()->route('community.chat.show', $conversation);
    }

    public function startDirect(Request $request, SpaceDirectory $directory): RedirectResponse
    {
        $validated = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);

        $me = $request->user();
        $other = User::query()->findOrFail($validated['user_id']);

        abort_if($other->is($me), 422);
        abort_unless($directory->hasAccess($other), 422, __('That person is not in the community.'));

        $existing = Conversation::query()
            ->where('type', 'direct')
            ->whereHas('members', fn ($q) => $q->where('users.id', $me->id))
            ->whereHas('members', fn ($q) => $q->where('users.id', $other->id))
            ->withCount('members')
            ->get()
            ->first(fn (Conversation $c) => $c->members_count === 2);

        if ($existing) {
            return redirect()->route('community.chat.show', $existing);
        }

        $conversation = DB::transaction(function () use ($me, $other) {
            $conversation = Conversation::query()->create(['type' => 'direct', 'created_by' => $me->id]);
            $conversation->members()->attach([$me->id, $other->id]);

            return $conversation;
        });

        return redirect()->route('community.chat.show', $conversation);
    }

    /**
     * Open (creating on first use) the channel of a chapter or committee
     * the person belongs to. Channels are kept on record.
     */
    public function channel(Request $request, Space $space, SpaceDirectory $directory): RedirectResponse
    {
        abort_unless(in_array($space->type, ['chapter', 'committee'], true), 404);
        abort_unless($directory->canPost($request->user(), $space), 403);

        $channel = Conversation::query()->firstOrCreate(
            ['type' => 'channel', 'space_id' => $space->id],
            ['title' => __(':name channel', ['name' => $space->name]), 'created_by' => $request->user()->id],
        );

        $channel->members()->syncWithoutDetaching([$request->user()->id]);

        return redirect()->route('community.chat.show', $channel);
    }

    /**
     * The active role's chapter channel (kept for existing links).
     */
    public function ensureChapterChannel(Request $request, SpaceDirectory $directory): RedirectResponse
    {
        $chapterSpace = $directory->forViewer()->first(fn (Space $s) => $s->isChapterSpace());
        abort_if($chapterSpace === null, 404, __('No chapter space for your active role.'));

        return $this->channel($request, $chapterSpace, $directory);
    }

    /**
     * A chat photo, for members of the conversation only. ?full=1 serves the
     * original; otherwise the light preview.
     */
    public function photo(Request $request, Conversation $conversation, Message $message): BinaryFileResponse
    {
        $this->authorizeMember($conversation, $request->user());
        abort_unless($message->conversation_id === $conversation->id && $message->hidden_at === null, 404);

        $media = $message->getFirstMedia('photo');
        abort_if($media === null, 404);

        $conversion = ! $request->boolean('full') && $media->hasGeneratedConversion('preview') ? 'preview' : '';

        return response()->file($media->getPath($conversion), [
            'Cache-Control' => 'private, max-age=604800, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function authorizeMember(Conversation $conversation, User $user): void
    {
        abort_unless($conversation->members()->where('users.id', $user->id)->exists(), 404);
    }

    private function markRead(Conversation $conversation, User $user, int $messageId): void
    {
        $conversation->members()->updateExistingPivot($user->id, [
            'last_read_at' => now(),
            'last_read_message_id' => DB::raw('case when last_read_message_id is null or last_read_message_id < '.(int) $messageId.' then '.(int) $messageId.' else last_read_message_id end'),
        ]);

        // Opening the chat clears its unread notice from the bell.
        $user->unreadNotifications()
            ->where('type', ChatMessageReceived::class)
            ->where('data->conversation_id', $conversation->id)
            ->update(['read_at' => now()]);
    }

    /**
     * For one-to-one chats: the last message the other person has seen,
     * unless they turned read receipts off.
     */
    private function seenUpTo(Conversation $conversation, User $viewer, NotificationPreferences $preferences): ?int
    {
        if ($conversation->type !== 'direct') {
            return null;
        }

        $other = $conversation->members()->whereKeyNot($viewer->id)->first();

        if (! $other || ! $preferences->setting($other, 'read_receipts', true) || ! $preferences->setting($viewer, 'read_receipts', true)) {
            return null;
        }

        $id = $other->getRelation('pivot')->getAttribute('last_read_message_id');

        return $id !== null ? (int) $id : null;
    }

    /**
     * One unread notice per conversation per person, not one per message.
     */
    private function notifyOthers(Conversation $conversation, Message $message): void
    {
        $conversation->members()
            ->whereKeyNot($message->sender_id)
            ->get()
            ->reject(fn (User $u) => $u->unreadNotifications()
                ->where('type', ChatMessageReceived::class)
                ->where('data->conversation_id', $conversation->id)
                ->exists())
            ->each(fn (User $u) => $u->notify(new ChatMessageReceived($conversation, $message)));
    }

    /**
     * @param  Collection<int, Conversation>  $conversations
     * @return array<int, int> conversation id => unread messages
     */
    private function unreadCounts(User $user, Collection $conversations): array
    {
        $counts = [];

        foreach ($conversations as $conversation) {
            $lastRead = $conversation->members->firstWhere('id', $user->id)?->getRelation('pivot')->getAttribute('last_read_message_id');

            $counts[$conversation->id] = $conversation->messages()
                ->where('sender_id', '!=', $user->id)
                ->when($lastRead, fn ($q) => $q->where('id', '>', $lastRead))
                ->count();
        }

        return $counts;
    }
}
