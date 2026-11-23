<?php

namespace Modules\Community\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Community\Models\Comment;
use Modules\Community\Models\Message;
use Modules\Community\Models\Post;
use Modules\Community\Models\Report;
use Modules\Community\Models\Space;
use Modules\Community\Models\Thread;
use Modules\Community\Models\ThreadReply;
use Modules\Community\Services\SpaceDirectory;

/**
 * COM-5: members report content; it goes to the moderators of the level it
 * sits at (chapter, state, national), who hide it or dismiss the report.
 * Reported direct messages go to national moderators, who see only the
 * reported message, never the rest of the chat.
 */
class ModerationController extends Controller
{
    /** @var array<string, class-string<Model>> */
    private const TYPES = [
        'post' => Post::class,
        'comment' => Comment::class,
        'thread' => Thread::class,
        'reply' => ThreadReply::class,
        'message' => Message::class,
    ];

    public function report(Request $request, SpaceDirectory $directory): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(array_keys(self::TYPES))],
            'id' => ['required', 'integer'],
            'reason' => ['required', Rule::in(array_keys(Report::REASONS))],
            'details' => ['nullable', 'string', 'max:1000'],
        ]);

        $model = self::TYPES[$validated['type']]::query()->findOrFail($validated['id']);
        $space = $this->spaceOf($model);

        abort_unless($this->canSee($request, $model, $space, $directory), 404);

        Report::query()->firstOrCreate(
            ['reporter_id' => $request->user()->id, 'reportable_type' => $model->getMorphClass(), 'reportable_id' => $model->getKey(), 'status' => 'open'],
            ['space_id' => $space?->id, 'reason' => $validated['reason'], 'details' => $validated['details'] ?? null],
        );

        return back()->with('status', __('Thanks. The moderators will look at it.'));
    }

    public function index(Request $request, SpaceDirectory $directory): View
    {
        $status = in_array($request->query('status'), ['actioned', 'dismissed'], true) ? $request->query('status') : 'open';

        $reports = Report::query()
            ->with(['reporter', 'space', 'reportable', 'handler'])
            ->where('status', $status)
            ->latest('id')
            ->limit(200)
            ->get()
            ->filter(fn (Report $r) => $r->space ? $directory->canModerate($request->user(), $r->space) : $this->moderatesAll($request, $directory))
            ->values();

        return view('community::moderation.index', ['reports' => $reports, 'status' => $status]);
    }

    public function decide(Request $request, Report $report, SpaceDirectory $directory): RedirectResponse|JsonResponse
    {
        abort_unless($report->space ? $directory->canModerate($request->user(), $report->space) : $this->moderatesAll($request, $directory), 403);

        $decision = $request->validate(['decision' => ['required', Rule::in(['hide', 'dismiss'])]])['decision'];

        DB::transaction(function () use ($report, $decision, $request) {
            if ($decision === 'hide' && $report->reportable && in_array('hidden_at', $report->reportable->getFillable(), true)) {
                $report->reportable->forceFill(['hidden_at' => now()])->save();
            }

            // Every open report on the same item is settled by one decision.
            Report::query()
                ->where('reportable_type', $report->reportable_type)
                ->where('reportable_id', $report->reportable_id)
                ->where('status', 'open')
                ->get()
                ->each(fn (Report $r) => $r->update([
                    'status' => $decision === 'hide' ? 'actioned' : 'dismissed',
                    'action' => $decision === 'hide' ? 'hidden' : null,
                    'handled_by' => $request->user()->id,
                    'handled_at' => now(),
                ]));
        });

        $status = $decision === 'hide' ? __('Hidden from members.') : __('Report dismissed.');

        return $request->expectsJson()
            ? response()->json(['status' => $status, 'remove' => '#report-'.$report->id])
            : back()->with('status', $status);
    }

    private function spaceOf(Model $model): ?Space
    {
        return match (true) {
            $model instanceof Post => $model->space,
            $model instanceof Comment => $model->post?->space,
            $model instanceof Thread => $model->forum?->space,
            $model instanceof ThreadReply => $model->thread?->forum?->space,
            $model instanceof Message => $model->conversation?->space,
            default => null,
        };
    }

    private function canSee(Request $request, Model $model, ?Space $space, SpaceDirectory $directory): bool
    {
        if ($model instanceof Message) {
            return $model->conversation?->members()->where('users.id', $request->user()->id)->exists() ?? false;
        }

        return $space !== null && $directory->canView($request->user(), $space);
    }

    private function moderatesAll(Request $request, SpaceDirectory $directory): bool
    {
        return $directory->canModerate($request->user(), $directory->ensureNationalSpace());
    }
}
