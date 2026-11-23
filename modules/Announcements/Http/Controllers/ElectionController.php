<?php

namespace Modules\Announcements\Http\Controllers;

use App\Enums\ScopeLevel;
use App\Http\Controllers\Controller;
use App\Models\Chapter;
use App\Models\School;
use App\Models\State;
use App\Support\Scope\ActiveContext;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Announcements\Models\Election;
use Modules\Announcements\Models\ElectionCandidate;
use Modules\Announcements\Models\ElectionVote;

/**
 * Chapter and state elections, one school one vote.
 *
 * Executives (events.manage) set up the ballot while it is a draft, open and
 * close voting. A proprietor casts the school's single vote; nobody can see
 * who a school voted for, and tallies appear only once voting has closed.
 */
class ElectionController extends Controller
{
    public function __construct(private readonly ActiveContext $context) {}

    public function index(): View
    {
        Gate::authorize('viewAny', Election::class);

        if ($this->context->allows('events.manage')) {
            $elections = Election::query()
                ->manageableBy($this->context)
                ->with(['chapter:id,name', 'state:id,name'])
                ->withCount(['candidates', 'votes'])
                ->latest()
                ->get();

            return view('announcements::elections.index', ['elections' => $elections, 'manager' => true, 'voted' => collect(), 'school' => null]);
        }

        $school = $this->school();
        $elections = Election::query()
            ->forSchool($school)
            ->with(['chapter:id,name', 'state:id,name'])
            ->withCount(['candidates', 'votes'])
            ->latest()
            ->get();

        return view('announcements::elections.index', [
            'elections' => $elections,
            'manager' => false,
            'school' => $school,
            'voted' => ElectionVote::query()->where('school_id', $school->id)->pluck('election_id')->flip(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Election::class);

        return view('announcements::elections.form', [
            'election' => new Election(['status' => Election::DRAFT]),
            'areas' => $this->areas(),
            'areaKey' => array_key_first($this->areas()),
            'candidates' => collect(),
            'schools' => collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Election::class);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'area' => ['required', 'string', Rule::in(array_keys($this->areas()))],
            'closes_at' => ['nullable', 'date', 'after:now'],
        ]);

        $election = Election::query()->create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'closes_at' => $data['closes_at'] ?? null,
            'status' => Election::DRAFT,
            'created_by' => $request->user()->id,
            ...$this->resolveArea($data['area']),
        ]);

        activity('elections')->performedOn($election)->causedBy($request->user())->log('created');

        return redirect()->route('elections.edit', $election)
            ->with('status', __('Election saved. Add the candidates, then open voting.'));
    }

    public function show(Election $election): View
    {
        Gate::authorize('view', $election);

        $election->load(['chapter:id,name', 'state:id,name']);

        $manager = Gate::allows('manage', $election);
        $school = $manager ? null : $this->school();

        $candidates = $election->candidates()->with('school:id,name')->withCount('votes')->get();
        $turnout = $election->votes()->count();
        $electorate = $election->electorate()->count();

        return view('announcements::elections.show', [
            'election' => $election,
            'candidates' => $candidates,
            'manager' => $manager,
            'school' => $school,
            'hasVoted' => $school ? $election->hasVoted($school) : false,
            'canVote' => $school !== null && Gate::allows('vote', $election),
            'turnout' => $turnout,
            'electorate' => $electorate,
            // Tallies stay hidden until voting has closed.
            'results' => $election->isClosed() ? $candidates->sortByDesc('votes_count')->values() : null,
            'voters' => $manager ? $election->votes()->with('school:id,name')->latest('cast_at')->get() : collect(),
        ]);
    }

    public function edit(Election $election): View
    {
        Gate::authorize('manage', $election);

        $election->load(['chapter:id,name', 'state:id,name']);

        return view('announcements::elections.form', [
            'election' => $election,
            'areas' => $this->areas(),
            'areaKey' => $election->chapter_id ? 'chapter:'.$election->chapter_id : 'state:'.$election->state_id,
            'candidates' => $election->candidates()->with('school:id,name')->get(),
            'schools' => $election->electorate()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Election $election): RedirectResponse
    {
        Gate::authorize('manage', $election);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'closes_at' => ['nullable', 'date'],
        ]);

        $election->fill($data)->save();

        return redirect()->route('elections.edit', $election)->with('status', __('Election updated.'));
    }

    public function destroy(Request $request, Election $election): RedirectResponse
    {
        Gate::authorize('manage', $election);

        if (! $election->isDraft()) {
            return back()->withErrors(['election' => __('Only an election that has not opened can be deleted.')]);
        }

        activity('elections')->performedOn($election)->causedBy($request->user())->log('deleted');
        $election->delete();

        return redirect()->route('elections.index')->with('status', __('Election deleted.'));
    }

    public function open(Request $request, Election $election): RedirectResponse
    {
        Gate::authorize('manage', $election);

        if (! $election->isDraft()) {
            return back();
        }

        if ($election->candidates()->count() < 2) {
            return back()->withErrors(['election' => __('Add at least two candidates before opening voting.')]);
        }

        if ($election->closes_at !== null && $election->closes_at->isPast()) {
            return back()->withErrors(['election' => __('The closing time has passed. Set a new closing time first.')]);
        }

        $election->forceFill(['status' => Election::OPEN])->save();

        activity('elections')->performedOn($election)->causedBy($request->user())->log('opened');

        return redirect()->route('elections.show', $election)->with('status', __('Voting is open.'));
    }

    public function close(Request $request, Election $election): RedirectResponse
    {
        Gate::authorize('manage', $election);

        $election->forceFill(['status' => Election::CLOSED])->save();

        activity('elections')->performedOn($election)->causedBy($request->user())
            ->withProperties(['votes' => $election->votes()->count()])
            ->log('closed');

        return redirect()->route('elections.show', $election)->with('status', __('Voting closed. Results are now visible.'));
    }

    public function storeCandidate(Request $request, Election $election): RedirectResponse
    {
        Gate::authorize('manage', $election);
        $this->ensureDraft($election);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'school_id' => ['nullable', 'integer', Rule::in($election->electorate()->pluck('id')->all())],
            'statement' => ['nullable', 'string', 'max:2000'],
        ]);

        $election->candidates()->create([
            ...$data,
            'sort' => (int) $election->candidates()->max('sort') + 1,
        ]);

        return redirect()->route('elections.edit', $election)->withFragment('candidates')->with('status', __('Candidate added.'));
    }

    public function destroyCandidate(Election $election, ElectionCandidate $candidate): RedirectResponse
    {
        Gate::authorize('manage', $election);
        $this->ensureDraft($election);
        abort_unless($candidate->election_id === $election->id, 404);

        $candidate->delete();

        return redirect()->route('elections.edit', $election)->withFragment('candidates')->with('status', __('Candidate removed.'));
    }

    /**
     * Cast the school's one vote. The (election, school) unique index is the
     * final guard if two taps race.
     */
    public function vote(Request $request, Election $election): RedirectResponse
    {
        Gate::authorize('vote', $election);

        $school = $this->school();

        if ($election->hasVoted($school)) {
            return redirect()->route('elections.show', $election)
                ->withErrors(['candidate_id' => __('Your school has already voted in this election.')]);
        }

        $candidateId = (int) $request->validate([
            'candidate_id' => ['required', 'integer', Rule::in($election->candidates()->pluck('id')->all())],
        ])['candidate_id'];

        try {
            DB::transaction(function () use ($election, $school, $request, $candidateId) {
                ElectionVote::query()->create([
                    'election_id' => $election->id,
                    'candidate_id' => $candidateId,
                    'school_id' => $school->id,
                    'cast_by' => $request->user()->id,
                    'cast_at' => now(),
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            return redirect()->route('elections.show', $election)
                ->withErrors(['candidate_id' => __('Your school has already voted in this election.')]);
        }

        // Record that the school voted, never who it voted for.
        activity('elections')->performedOn($election)->causedBy($request->user())
            ->withProperties(['school_id' => $school->id])
            ->log('vote cast');

        return redirect()->route('elections.show', $election)->with('status', __('Your vote has been recorded. Thank you.'));
    }

    /**
     * What the active executive may run an election for.
     *
     * @return array<string, string> "state:{id}" | "chapter:{id}" => label
     */
    private function areas(): array
    {
        $areas = [];

        switch ($this->context->level()) {
            case ScopeLevel::National:
                foreach (State::query()->orderBy('name')->get(['id', 'name']) as $state) {
                    $areas['state:'.$state->id] = __(':name State (every school)', ['name' => $state->name]);
                }
                foreach (Chapter::query()->with('state:id,name')->orderBy('name')->get() as $chapter) {
                    $areas['chapter:'.$chapter->id] = $chapter->name.' ('.$chapter->state->name.')';
                }
                break;

            case ScopeLevel::State:
                $state = State::query()->find($this->context->stateId());
                if ($state) {
                    $areas['state:'.$state->id] = __(':name State (every school)', ['name' => $state->name]);
                    foreach (Chapter::query()->where('state_id', $state->id)->orderBy('name')->get(['id', 'name']) as $chapter) {
                        $areas['chapter:'.$chapter->id] = $chapter->name;
                    }
                }
                break;

            case ScopeLevel::Chapter:
                $chapter = Chapter::query()->find($this->context->chapterId());
                if ($chapter) {
                    $areas['chapter:'.$chapter->id] = $chapter->name;
                }
                break;

            default:
                break;
        }

        return $areas;
    }

    /** @return array{state_id: int|null, chapter_id: int|null} */
    private function resolveArea(string $key): array
    {
        [$type, $id] = explode(':', $key, 2);

        if ($type === 'chapter') {
            $chapter = Chapter::query()->findOrFail((int) $id);

            return ['state_id' => $chapter->state_id, 'chapter_id' => $chapter->id];
        }

        return ['state_id' => (int) $id, 'chapter_id' => null];
    }

    private function ensureDraft(Election $election): void
    {
        if (! $election->isDraft()) {
            throw ValidationException::withMessages([
                'name' => __('Candidates cannot be changed once voting has opened.'),
            ]);
        }
    }

    private function school(): School
    {
        $id = $this->context->schoolId();
        abort_if($id === null, 403, __('Switch to a school role to vote.'));

        return School::withoutGlobalScopes()->findOrFail($id);
    }
}
