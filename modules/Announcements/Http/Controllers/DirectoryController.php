<?php

namespace Modules\Announcements\Http\Controllers;

use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Enums\ScopeLevel;
use App\Http\Controllers\Controller;
use App\Models\Chapter;
use App\Models\RoleAssignment;
use App\Models\School;
use App\Support\Scope\ActiveContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Member directory: the active member schools (and their proprietors) around
 * you. A school-level or chapter role sees its own chapter; a state executive
 * sees the state; national roles can browse every chapter.
 *
 * Proprietors cannot normally read other schools (the school scope narrows
 * them to their own), so this page queries the registry itself and exposes
 * only directory fields: name, place, levels, membership number, school phone.
 */
class DirectoryController extends Controller
{
    public function __construct(private readonly ActiveContext $context) {}

    public function index(Request $request): View
    {
        Gate::authorize('directory.view');

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'chapter_id' => ['nullable', 'integer'],
        ]);

        $chapterId = $this->context->chapterId();
        $stateId = $this->context->stateId();
        $level = $this->context->level();

        abort_unless($chapterId !== null || $stateId !== null || $level === ScopeLevel::National, 403);

        // Chapters the viewer may browse; a chapter-bound role has exactly one.
        $chapters = Chapter::query()
            ->with('state:id,name')
            ->when($chapterId !== null, fn (Builder $q) => $q->whereKey($chapterId))
            ->when($chapterId === null && $stateId !== null, fn (Builder $q) => $q->where('state_id', $stateId))
            ->orderBy('name')
            ->get();

        $selected = $chapterId ?? (isset($filters['chapter_id']) ? (int) $filters['chapter_id'] : null);
        $selected = $selected !== null && $chapters->contains('id', $selected) ? $selected : null;
        $term = trim((string) ($filters['q'] ?? ''));

        $schools = School::withoutGlobalScopes()
            ->with('chapter:id,name')
            ->whereIn('membership_status', [MembershipStatus::Active->value, MembershipStatus::Owing->value])
            ->whereIn('chapter_id', $selected !== null ? [$selected] : $chapters->pluck('id'))
            ->when($term !== '', fn (Builder $q) => $q->where(function (Builder $w) use ($term) {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term).'%';
                $w->where('name', 'like', $like)->orWhere('lga', 'like', $like);
            }))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $proprietors = RoleAssignment::query()
            ->with('user:id,name')
            ->where('scope_type', ScopeLevel::School->value)
            ->whereIn('scope_id', $schools->pluck('id'))
            ->whereHas('role', fn (Builder $q) => $q->where('name', Role::Proprietor->value))
            ->get()
            ->groupBy('scope_id')
            ->map(fn ($rows) => $rows->pluck('user.name')->filter()->unique()->values()->all());

        return view('announcements::directory.index', [
            'schools' => $schools,
            'proprietors' => $proprietors,
            'chapters' => $chapters,
            'selected' => $selected,
            'term' => $term,
            'fixedChapter' => $chapterId !== null ? $chapters->first() : null,
        ]);
    }
}
