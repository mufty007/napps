<?php

namespace Modules\Community\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Community\Models\Poll;
use Modules\Community\Services\PollService;
use Modules\Community\Services\SpaceDirectory;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Voting on polls and formal votes, closing them, and exporting results.
 */
class PollController extends Controller
{
    public function vote(Request $request, Poll $poll, PollService $polls, SpaceDirectory $directory): JsonResponse|RedirectResponse
    {
        $this->loadFor($poll);
        abort_unless($directory->canView($request->user(), $poll->post->space), 404);

        $validated = $request->validate([
            'options' => ['required', 'array', 'min:1', 'max:10'],
            'options.*' => ['integer'],
            'school_id' => ['nullable', 'integer'],
        ], ['options.required' => __('Choose an option.')]);

        $polls->vote($request->user(), $poll, $validated['options'], isset($validated['school_id']) ? (int) $validated['school_id'] : null);

        return $this->respond($request, $poll, $poll->formal ? __('Your ballot is cast.') : __('Vote saved.'));
    }

    public function close(Request $request, Poll $poll, PollService $polls, SpaceDirectory $directory): JsonResponse|RedirectResponse
    {
        $this->loadFor($poll);
        $user = $request->user();
        $space = $poll->post->space;

        abort_unless(
            $poll->post->author_id === $user->id || $directory->canRunFormalVote($user, $space) || $directory->canModerate($user, $space),
            403
        );

        $polls->close($poll, $user);

        return $this->respond($request, $poll, $poll->formal ? __('Voting closed. The results are final.') : __('Poll closed.'));
    }

    public function export(Request $request, Poll $poll, PollService $polls, SpaceDirectory $directory): StreamedResponse
    {
        $this->loadFor($poll);
        $state = $polls->present($poll, $request->user(), $directory);
        abort_unless($state['canExport'], 403);

        activity('community')->performedOn($poll)->causedBy($request->user())->event('exported')->log('Vote results exported');

        return $polls->export($poll);
    }

    private function loadFor(Poll $poll): void
    {
        $poll->load(['post.space', 'options']);
    }

    private function respond(Request $request, Poll $poll, string $status): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'status' => $status,
                'html' => view('community::polls.card', ['poll' => $poll->refresh()->load(['post.space', 'options'])])->render(),
            ]);
        }

        return redirect()->to(route('community.posts.show', $poll->post).'#poll-'.$poll->id)->with('status', $status);
    }
}
