<?php

namespace Modules\Community\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;
use Modules\Community\Models\Forum;
use Modules\Community\Models\Space;
use Modules\Community\Models\Thread;
use Modules\Community\Models\ThreadReply;
use Modules\Community\Notifications\ThreadActivity;
use Modules\Community\Services\SpaceDirectory;

/**
 * COM-2: topic forums at chapter, state and national level. Questions get
 * answers, upvotes and a best answer; answered threads form a searchable
 * knowledge base.
 */
class ForumController extends Controller
{
    public function index(Request $request, SpaceDirectory $directory): View
    {
        $spaces = $directory->forViewer()->filter(fn (Space $s) => $s->isAutomatic())->values();
        $spaces->each(fn (Space $s) => $directory->ensureDefaultForums($s));

        $search = trim((string) $request->query('q'));
        $forumIds = Forum::query()->whereIn('space_id', $spaces->pluck('id'))->pluck('id');

        return view('community::forums.index', [
            'spaces' => $spaces,
            'forums' => Forum::query()
                ->whereIn('space_id', $spaces->pluck('id'))
                ->withCount(['threads' => fn (Builder $q) => $q->whereNull('hidden_at')])
                ->withCount(['threads as open_questions_count' => fn (Builder $q) => $q->whereNull('hidden_at')->where('is_question', true)->whereNull('best_comment_id')])
                ->withMax('threads as last_activity', 'last_activity_at')
                ->orderBy('name')
                ->get()
                ->groupBy('space_id'),
            'search' => $search,
            'results' => mb_strlen($search) >= 3
                ? Thread::query()
                    ->whereIn('forum_id', $forumIds)
                    ->whereNull('hidden_at')
                    ->where(fn (Builder $q) => $q->where('title', 'like', '%'.$search.'%')->orWhere('body', 'like', '%'.$search.'%'))
                    ->with(['forum.space', 'author'])
                    ->withCount('replies')
                    ->orderByDesc('best_comment_id')
                    ->latest('last_activity_at')
                    ->limit(20)
                    ->get()
                : collect(),
        ]);
    }

    public function show(Request $request, Forum $forum, SpaceDirectory $directory): View
    {
        abort_unless($directory->canView($request->user(), $forum->space), 404);

        $filter = in_array($request->query('filter'), ['questions', 'unanswered', 'answered'], true) ? $request->query('filter') : 'all';

        $threads = $forum->threads()
            ->whereNull('hidden_at')
            ->with(['author'])
            ->withCount('replies')
            ->when($filter === 'questions', fn (Builder $q) => $q->where('is_question', true))
            ->when($filter === 'unanswered', fn (Builder $q) => $q->where('is_question', true)->whereNull('best_comment_id'))
            ->when($filter === 'answered', fn (Builder $q) => $q->whereNotNull('best_comment_id'))
            ->orderByRaw('coalesce(last_activity_at, created_at) desc')
            ->paginate(20)
            ->withQueryString();

        return view('community::forums.show', [
            'forum' => $forum->load('space'),
            'threads' => $threads,
            'filter' => $filter,
            'canPost' => $directory->canPost($request->user(), $forum->space),
        ]);
    }

    public function storeThread(Request $request, Forum $forum, SpaceDirectory $directory): RedirectResponse
    {
        abort_unless($directory->canPost($request->user(), $forum->space), 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'min:8', 'max:180'],
            'body' => ['required', 'string', 'min:5', 'max:10000'],
            'is_question' => ['sometimes', 'boolean'],
        ]);

        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $request->user()->id,
            'title' => trim($validated['title']),
            'body' => trim($validated['body']),
            'is_question' => $request->boolean('is_question'),
            'last_activity_at' => now(),
        ]);

        $thread->followers()->syncWithoutDetaching([$request->user()->id]);

        return redirect()
            ->route('community.forums.threads.show', [$forum, $thread])
            ->with('status', $thread->is_question ? __('Question posted. You will be notified of answers.') : __('Thread started.'));
    }

    public function showThread(Request $request, Forum $forum, Thread $thread, SpaceDirectory $directory): View
    {
        abort_unless($thread->forum_id === $forum->id, 404);
        abort_unless($directory->canView($request->user(), $forum->space), 404);
        $canModerate = $directory->canModerate($request->user(), $forum->space);
        abort_if($thread->hidden_at && ! $canModerate, 404);

        $replies = $thread->replies()
            ->with('author')
            ->when(! $canModerate, fn (Builder $q) => $q->whereNull('hidden_at'))
            ->orderByRaw('case when id = ? then 0 else 1 end', [(int) $thread->best_comment_id])
            ->orderByDesc('votes_count')
            ->oldest()
            ->get();

        return view('community::forums.thread', [
            'forum' => $forum->load('space'),
            'thread' => $thread->load('author'),
            'replies' => $replies,
            'votedIds' => DB::table('community_votes')->where('user_id', $request->user()->id)->whereIn('thread_reply_id', $replies->modelKeys())->pluck('thread_reply_id')->map(fn ($id) => (int) $id)->all(),
            'following' => $thread->followers()->where('users.id', $request->user()->id)->exists(),
            'canPost' => $directory->canPost($request->user(), $forum->space) && ! $thread->isLocked(),
            'canMarkBest' => $thread->is_question && ($thread->author_id === $request->user()->id || $canModerate),
            'canModerate' => $canModerate,
        ]);
    }

    public function reply(Request $request, Forum $forum, Thread $thread, SpaceDirectory $directory): RedirectResponse
    {
        abort_unless($thread->forum_id === $forum->id, 404);
        abort_unless($directory->canPost($request->user(), $forum->space), 403);

        if ($thread->isLocked()) {
            return back()->with('error', __('This thread is locked.'));
        }

        $validated = $request->validate(['body' => ['required', 'string', 'min:2', 'max:10000']]);

        $reply = $thread->replies()->create(['author_id' => $request->user()->id, 'body' => trim($validated['body'])]);
        $thread->forceFill(['last_activity_at' => now()])->saveQuietly();

        Notification::send(
            $thread->followers()->whereKeyNot($request->user()->id)->get(),
            new ThreadActivity($thread, $reply->setRelation('author', $request->user())),
        );

        $thread->followers()->syncWithoutDetaching([$request->user()->id]);

        return redirect()->to(route('community.forums.threads.show', [$forum, $thread]).'#reply-'.$reply->id);
    }

    public function vote(Request $request, Forum $forum, Thread $thread, ThreadReply $reply, SpaceDirectory $directory): JsonResponse|RedirectResponse
    {
        abort_unless($thread->forum_id === $forum->id && $reply->thread_id === $thread->id, 404);
        abort_unless($directory->canView($request->user(), $forum->space), 403);
        abort_if($reply->author_id === $request->user()->id, 422, __('You cannot upvote your own answer.'));

        $voted = DB::transaction(function () use ($reply, $request) {
            $removed = $reply->voters()->detach($request->user()->id);

            if (! $removed) {
                $reply->voters()->attach($request->user()->id);
            }

            $reply->forceFill(['votes_count' => $reply->voters()->count()])->saveQuietly();

            return ! $removed;
        });

        return $request->expectsJson()
            ? response()->json(['voted' => $voted, 'count' => $reply->votes_count])
            : back();
    }

    public function follow(Request $request, Forum $forum, Thread $thread, SpaceDirectory $directory): JsonResponse|RedirectResponse
    {
        abort_unless($thread->forum_id === $forum->id && $directory->canView($request->user(), $forum->space), 404);

        $changes = $thread->followers()->toggle([$request->user()->id]);
        $following = (bool) $changes['attached'];
        $status = $following ? __('Following. You will be notified of new answers.') : __('Unfollowed.');

        return $request->expectsJson()
            ? response()->json(['following' => $following, 'status' => $status])
            : back()->with('status', $status);
    }

    public function markBest(Request $request, Forum $forum, Thread $thread, ThreadReply $reply, SpaceDirectory $directory): JsonResponse|RedirectResponse
    {
        abort_unless($thread->forum_id === $forum->id && $reply->thread_id === $thread->id, 404);
        abort_unless($thread->is_question && ($thread->author_id === $request->user()->id || $directory->canModerate($request->user(), $forum->space)), 403);

        $unmark = (int) $thread->best_comment_id === $reply->id;

        $thread->forceFill([
            'best_comment_id' => $unmark ? null : $reply->id,
            'answered_at' => $unmark ? null : now(),
        ])->save();

        if (! $unmark && $reply->author_id !== $request->user()->id) {
            $reply->author?->notify(new ThreadActivity($thread, $reply, bestAnswer: true));
        }

        $status = $unmark ? __('Best answer cleared.') : __('Marked as the best answer.');
        $url = route('community.forums.threads.show', [$forum, $thread]).'#reply-'.$reply->id;

        return $request->expectsJson()
            ? response()->json(['best' => ! $unmark, 'reply_id' => $reply->id, 'status' => $status, 'redirect' => $url])
            : redirect()->to($url)->with('status', $status);
    }

    public function lock(Request $request, Forum $forum, Thread $thread, SpaceDirectory $directory): JsonResponse|RedirectResponse
    {
        abort_unless($thread->forum_id === $forum->id && $directory->canModerate($request->user(), $forum->space), 403);

        $thread->forceFill(['locked_at' => $thread->isLocked() ? null : now()])->save();
        activity('moderation')->performedOn($thread)->event($thread->isLocked() ? 'locked' : 'unlocked')->log('Thread lock changed');

        $status = $thread->isLocked() ? __('Thread locked.') : __('Thread reopened.');

        return $request->expectsJson()
            ? response()->json(['locked' => $thread->isLocked(), 'status' => $status])
            : back()->with('status', $status);
    }
}
