<?php

namespace Modules\Community\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Community\Models\Comment;
use Modules\Community\Models\Forum;
use Modules\Community\Models\Post;
use Modules\Community\Models\Reaction;
use Modules\Community\Models\Space;
use Modules\Community\Models\Thread;
use Modules\Community\Notifications\PostReplied;
use Modules\Community\Services\PollService;
use Modules\Community\Services\SpaceDirectory;

/**
 * COM-1: the home feed (chapter, state, national and joined groups), posts,
 * comments with replies, likes and pinned official posts.
 */
class FeedController extends Controller
{
    public const LEVELS = ['all', 'chapter', 'state', 'national', 'groups'];

    public function index(Request $request, SpaceDirectory $directory): View
    {
        $spaces = $directory->forViewer();
        $level = in_array($request->query('level'), self::LEVELS, true) ? $request->query('level') : 'all';
        $sort = $request->query('sort') === 'active' ? 'active' : 'latest';
        $spaceFilter = $spaces->firstWhere('id', $request->integer('space'));

        $sources = $spaceFilter
            ? collect([$spaceFilter])
            : $spaces->filter(fn (Space $s) => match ($level) {
                'groups' => $s->isGroup(),
                'all' => true,
                default => $s->type === $level,
            });

        $posts = Post::query()
            ->visible()
            ->whereIn('space_id', $sources->pluck('id'))
            ->with(['author', 'space', 'poll.options'])
            ->withCount(['comments' => fn (Builder $q) => $q->whereNull('hidden_at'), 'reactions'])
            ->orderByDesc('pinned')
            ->when($sort === 'active', fn (Builder $q) => $q->orderByRaw('coalesce(last_activity_at, created_at) desc'))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('community::feed.index', [
            'posts' => $posts,
            'spaces' => $spaces,
            'postable' => $postable = $spaces->filter(fn (Space $s) => $directory->canPost($request->user(), $s))->values(),
            'formalSpaceIds' => $postable->filter(fn (Space $s) => $directory->canRunFormalVote($request->user(), $s))->pluck('id')->all(),
            'level' => $level,
            'sort' => $sort,
            'spaceFilter' => $spaceFilter,
            'likedIds' => $this->likedIds($request, $posts->getCollection()->modelKeys()),
            'directory' => $directory,
            // Right rail (LinkedIn / Facebook style): questions waiting for an answer, groups to join.
            'openQuestions' => Thread::query()
                ->whereIn('forum_id', Forum::query()->select('id')->whereIn('space_id', $spaces->pluck('id')))
                ->where('is_question', true)->whereNull('best_comment_id')->whereNull('hidden_at')
                ->with('forum')->withCount('replies')->latest('id')->limit(4)->get(),
            'suggestedGroups' => Space::query()
                ->where('type', 'interest')->whereIn('privacy', ['open', 'request'])
                ->whereNotIn('id', $spaces->pluck('id'))
                ->withCount('activeMembers')->orderByDesc('active_members_count')->limit(3)->get(),
            'unreadNotifications' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function show(Request $request, Post $post, SpaceDirectory $directory): View
    {
        $post->load(['author', 'space', 'poll.options'])->loadCount('reactions');
        abort_unless($directory->canView($request->user(), $post->space), 404);
        abort_if($post->hidden_at && $post->author_id !== $request->user()->id && ! $directory->canModerate($request->user(), $post->space), 404);

        $comments = $post->comments()
            ->whereNull('parent_id')
            ->with(['author', 'replies' => fn ($q) => $q->with('author')->oldest()])
            ->oldest()
            ->get();

        return view('community::feed.show', [
            'post' => $post,
            'comments' => $comments,
            'liked' => in_array($post->id, $this->likedIds($request, [$post->id]), true),
            'canPost' => $directory->canPost($request->user(), $post->space),
            'directory' => $directory,
        ]);
    }

    public function store(Request $request, SpaceDirectory $directory, PollService $polls): RedirectResponse|JsonResponse
    {
        $isPoll = $request->input('type') === 'poll';
        if ($isPoll) {
            // Blank option rows from the composer are ignored.
            $request->merge(['options' => array_values(array_filter(
                array_map(fn ($o) => is_string($o) ? trim($o) : $o, (array) $request->input('options', [])),
                fn ($o) => $o !== '' && $o !== null
            ))]);
        }

        $validated = $request->validate([
            'space_id' => ['required', 'integer'],
            'body' => ['required', 'string', 'min:2', 'max:5000'],
            'type' => ['nullable', 'in:text,question,showcase,poll'],
            'options' => ['exclude_unless:type,poll', 'required', 'array', 'min:2', 'max:6'],
            'options.*' => ['exclude_unless:type,poll', 'string', 'max:120', 'distinct:ignore_case'],
            'multiple' => ['exclude_unless:type,poll', 'nullable', 'boolean'],
            'anonymous' => ['exclude_unless:type,poll', 'nullable', 'boolean'],
            'formal' => ['exclude_unless:type,poll', 'nullable', 'boolean'],
            'closes_at' => ['exclude_unless:type,poll', Rule::requiredIf($request->boolean('formal')), 'nullable', 'date', 'after:now', 'before:+90 days'],
        ], [
            'options.required' => __('Add at least two options.'),
            'options.min' => __('Add at least two options.'),
            'options.*.distinct' => __('Each option must be different.'),
            'closes_at.required' => __('A formal vote needs a closing time.'),
        ]);

        $space = Space::query()->find($validated['space_id']);

        if (! $space || ! $directory->canPost($request->user(), $space)) {
            return back()->withInput()->withErrors(['space_id' => __('You cannot post there.')]);
        }

        $formal = $isPoll && $request->boolean('formal');

        if ($formal && ! $directory->canRunFormalVote($request->user(), $space)) {
            return back()->withInput()->withErrors(['formal' => __('Only executives of :space can run a formal vote there.', ['space' => $space->name])]);
        }

        $post = DB::transaction(function () use ($request, $validated, $space, $isPoll, $formal, $polls) {
            $post = Post::query()->create([
                'space_id' => $space->id,
                'author_id' => $request->user()->id,
                'type' => $validated['type'] ?? 'text',
                'body' => trim($validated['body']),
                'last_activity_at' => now(),
            ]);

            if ($isPoll) {
                $polls->create($post, [...$validated, 'formal' => $formal]);
            }

            if ($formal) {
                // Formal votes are official business: keep them at the top of the space.
                $post->forceFill(['pinned' => true, 'pinned_by' => $request->user()->id])->save();
            }

            return $post;
        });

        $status = __('Posted to :space.', ['space' => $space->name]);
        $post->load(['author', 'space', 'poll.options']);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => $status,
                'html' => view('community::partials.post-card', [
                    'post' => $post,
                    'liked' => false,
                    'directory' => $directory,
                ])->render(),
                'redirect' => route('community.posts.show', $post),
            ]);
        }

        return redirect()->route('community.posts.show', $post)->with('status', $status);
    }

    public function comment(Request $request, Post $post, SpaceDirectory $directory): RedirectResponse|JsonResponse
    {
        abort_unless($directory->canPost($request->user(), $post->space), 403);

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:3000'],
            'parent_id' => ['nullable', 'integer'],
        ]);

        $parent = isset($validated['parent_id'])
            ? $post->comments()->whereNull('parent_id')->find($validated['parent_id'])
            : null;

        $comment = $post->comments()->create([
            'author_id' => $request->user()->id,
            'parent_id' => $parent?->id,
            'body' => trim($validated['body']),
        ]);

        $post->forceFill(['last_activity_at' => now()])->saveQuietly();

        $notified = [$request->user()->id];

        if ($parent && ! in_array($parent->author_id, $notified, true)) {
            $parent->author?->notify(new PostReplied($post, $comment, toCommentAuthor: true));
            $notified[] = $parent->author_id;
        }

        if (! in_array($post->author_id, $notified, true)) {
            $post->author?->notify(new PostReplied($post, $comment));
        }

        if ($request->expectsJson()) {
            return response()->json(['html' => view('community::partials.comment-item', [
                'comment' => $comment->setRelation('author', $request->user())->setRelation('replies', collect()),
                'post' => $post,
                'directory' => $directory,
                'isReply' => $parent !== null,
                'canPost' => true,
            ])->render()]);
        }

        return redirect()->to(route('community.posts.show', $post).'#comment-'.$comment->id);
    }

    /**
     * The comment thread for a post, loaded inline in the feed.
     */
    public function comments(Request $request, Post $post, SpaceDirectory $directory): View
    {
        abort_unless($directory->canView($request->user(), $post->space), 404);

        return view('community::partials.comment-thread', [
            'post' => $post,
            'comments' => $this->threadFor($post),
            'canPost' => $directory->canPost($request->user(), $post->space),
            'directory' => $directory,
        ]);
    }

    /**
     * @return Collection<int, Comment>
     */
    private function threadFor(Post $post): Collection
    {
        return $post->comments()
            ->whereNull('parent_id')
            ->with(['author', 'replies' => fn ($q) => $q->with('author')->oldest()])
            ->oldest()
            ->get();
    }

    public function react(Request $request, Post $post, SpaceDirectory $directory): JsonResponse|RedirectResponse
    {
        abort_unless($directory->canView($request->user(), $post->space), 403);

        $attributes = [
            'user_id' => $request->user()->id,
            'reactable_type' => $post->getMorphClass(),
            'reactable_id' => $post->id,
            'type' => 'like',
        ];

        $existing = Reaction::query()->where($attributes)->first();
        $existing ? $existing->delete() : Reaction::query()->create($attributes);

        if ($request->expectsJson()) {
            return response()->json(['liked' => ! $existing, 'count' => $post->reactions()->count()]);
        }

        return back();
    }

    public function pin(Request $request, Post $post, SpaceDirectory $directory): RedirectResponse
    {
        abort_unless($directory->canPin($request->user(), $post->space), 403);

        $post->forceFill([
            'pinned' => ! $post->pinned,
            'pinned_by' => $post->pinned ? null : $request->user()->id,
        ])->save();

        activity('moderation')->performedOn($post)->event($post->pinned ? 'pinned' : 'unpinned')->log('Pinned state changed');

        return back()->with('status', $post->pinned ? __('Pinned to the top of :space.', ['space' => $post->space->name]) : __('Unpinned.'));
    }

    public function destroy(Request $request, Post $post, SpaceDirectory $directory): RedirectResponse
    {
        $own = $post->author_id === $request->user()->id;
        abort_unless($own || $directory->canModerate($request->user(), $post->space), 403);

        if ($own) {
            $post->delete();
        } else {
            $post->forceFill(['hidden_at' => now()])->save();
            activity('moderation')->performedOn($post)->event('hidden')->log('Post hidden by moderator');
        }

        return redirect()->route('community.feed')->with('status', $own ? __('Post deleted.') : __('Post hidden from members.'));
    }

    public function destroyComment(Request $request, Comment $comment, SpaceDirectory $directory): RedirectResponse
    {
        $post = $comment->post;
        $own = $comment->author_id === $request->user()->id;
        abort_unless($own || $directory->canModerate($request->user(), $post->space), 403);

        $own ? $comment->delete() : $comment->forceFill(['hidden_at' => now()])->save();

        return redirect()->route('community.posts.show', $post)->with('status', __('Comment removed.'));
    }

    /**
     * @param  list<int>  $postIds
     * @return list<int>
     */
    private function likedIds(Request $request, array $postIds): array
    {
        return Reaction::query()
            ->where('user_id', $request->user()->id)
            ->where('reactable_type', (new Post)->getMorphClass())
            ->whereIn('reactable_id', $postIds)
            ->pluck('reactable_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
