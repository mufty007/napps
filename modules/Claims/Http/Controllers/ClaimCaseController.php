<?php

namespace Modules\Claims\Http\Controllers;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\User;
use App\Support\Money;
use App\Support\PhoneNumber;
use App\Support\Scope\ActiveContext;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Claims\Enums\ClaimEventType;
use Modules\Claims\Enums\ClaimStage;
use Modules\Claims\Exceptions\ClaimsNotEnabled;
use Modules\Claims\Http\Controllers\Concerns\RespondsToAjax;
use Modules\Claims\Models\ClaimCase;
use Modules\Claims\Rules\NairaAmount;
use Modules\Claims\Services\ClaimService;

class ClaimCaseController extends Controller
{
    use RespondsToAjax;

    public function __construct(private readonly ClaimService $claims) {}

    /**
     * Case list with Open/Closed tabs, stage pills and search. Ajax requests
     * (the tabs, pills, search box and pager) get just the results block.
     */
    public function index(Request $request, ActiveContext $context): View|Response
    {
        Gate::authorize('viewAny', ClaimCase::class);

        $status = $request->query('status') === 'closed' ? 'closed' : 'open';
        $stage = ClaimStage::tryFrom((string) $request->query('stage'));
        $search = trim((string) $request->query('q'));

        $visible = fn () => ClaimCase::query()->visibleTo($context);
        $inStatus = fn () => $visible()
            ->when($status === 'closed', fn ($q) => $q->closed(), fn ($q) => $q->open())
            ->search($search);

        $cases = $inStatus()
            ->with(['school', 'lawyer', 'hearings'])
            ->when($stage, fn ($q, ClaimStage $s) => $q->where('stage', $s->value))
            ->latest('escalated_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $data = [
            'cases' => $cases,
            'status' => $status,
            'stage' => $stage,
            'search' => $search,
            'counts' => ['open' => $visible()->open()->count(), 'closed' => $visible()->closed()->count()],
            'stageCounts' => $inStatus()->toBase()->selectRaw('stage, count(*) as total')->groupBy('stage')->pluck('total', 'stage'),
            'stages' => $status === 'closed' ? [] : array_values(array_filter(ClaimStage::pipeline(), fn (ClaimStage $s) => ! $s->isClosed())),
            'canOpen' => Gate::allows('create', ClaimCase::class),
        ];

        if ($request->ajax()) {
            return response()->view('claims::cases.partials.results', $data);
        }

        return view('claims::cases.index', $data + [
            'canManageSettings' => Gate::allows('manageSettings', ClaimCase::class),
        ]);
    }

    public function create(ActiveContext $context): View
    {
        Gate::authorize('create', ClaimCase::class);

        $school = $context->schoolId() ? School::query()->with('state')->find($context->schoolId()) : null;

        return view('claims::cases.create', [
            'school' => $school,
            'schools' => $school ? collect() : School::query()->orderBy('name')->limit(500)->pluck('name', 'id'),
            'enabled' => $school ? $this->claims->isStateEnabled($school->state_id) : true,
        ]);
    }

    public function store(Request $request, ActiveContext $context): RedirectResponse
    {
        Gate::authorize('create', ClaimCase::class);

        $rules = [
            'debtor_name' => ['required', 'string', 'max:255'],
            'debtor_phone' => ['nullable', 'string', 'max:30', function (string $attribute, mixed $value, \Closure $fail) {
                if (filled($value) && PhoneNumber::normalize((string) $value) === null) {
                    $fail(__('Enter a valid Nigerian phone number.'));
                }
            }],
            'amount' => ['required', new NairaAmount],
            'days_overdue' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'summary' => ['nullable', 'string', 'max:5000'],
        ];

        $schoolId = $context->schoolId();

        if ($schoolId === null) {
            $rules['school_id'] = ['required', 'integer'];
        }

        $validated = $request->validate($rules);

        // School::query() is scoped to the active role, so a foreign id simply is not found.
        $school = School::query()->with('state')->find($schoolId ?? $validated['school_id']);

        if ($school === null) {
            return back()->withInput()->withErrors(['school_id' => __('Choose one of your schools.')]);
        }

        try {
            $case = $this->claims->openCase($request->user(), $school, [
                'debtor_name' => $validated['debtor_name'],
                'debtor_phone' => $validated['debtor_phone'] ?? null,
                'amount_kobo' => (int) Money::toKobo($validated['amount']),
                'days_overdue' => $validated['days_overdue'] ?? null,
                'summary' => $validated['summary'] ?? null,
            ]);
        } catch (ClaimsNotEnabled $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('claims.show', $case)->with('status', __('Claim :ref opened.', ['ref' => $case->reference]));
    }

    public function show(ClaimCase $case): View
    {
        Gate::authorize('view', $case);

        $case->load(['school.state', 'school.chapter', 'lawyer', 'opener', 'hearings', 'settlements.recorder', 'fees.recorder', 'media']);

        $showNotes = Gate::allows('viewNotes', $case);

        $events = $case->events()
            ->with('user')
            ->when(! $showNotes, fn ($q) => $q->where('type', '!=', ClaimEventType::Note->value))
            ->limit(200)
            ->get();

        $canAssign = Gate::allows('assign', $case);

        return view('claims::cases.show', [
            'case' => $case,
            'events' => $events,
            'checklist' => $this->claims->buildPackChecklist($case),
            'totals' => $this->claims->totals($case),
            'stages' => ClaimStage::pipeline(),
            'canHandle' => Gate::allows('handle', $case),
            'canContribute' => Gate::allows('contribute', $case),
            'canAssign' => $canAssign,
            'lawyers' => $canAssign ? User::query()->holdingRole(Role::LegalPanel)->orderBy('name')->pluck('name', 'id') : collect(),
            'nextHearing' => $case->nextHearing(),
            'canGenerate' => class_exists('Modules\\Templates\\Services\\TemplateService'),
        ]);
    }

    /**
     * Stage, court details and a note in one go. Answers JSON for the ajax form.
     */
    public function update(Request $request, ClaimCase $case): RedirectResponse|JsonResponse
    {
        Gate::authorize('handle', $case);

        $validated = $request->validate([
            'stage' => ['nullable', Rule::enum(ClaimStage::class)],
            'stage_note' => ['nullable', 'string', 'max:2000'],
            'court_name' => ['nullable', 'string', 'max:255'],
            'court_file_number' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $changes = $this->claims->updateCase($case, $request->user(), $validated);

        return $this->respond($request, $changes === [] ? __('Nothing changed.') : implode(' ', $changes), [
            'stage' => $case->fresh()->stage->value,
        ]);
    }

    public function note(Request $request, ClaimCase $case): RedirectResponse|JsonResponse
    {
        Gate::authorize('handle', $case);

        $validated = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $this->claims->addNote($case, $request->user(), $validated['body']);

        return $this->respond($request, __('Note added.'));
    }

    public function assign(Request $request, ClaimCase $case): RedirectResponse|JsonResponse
    {
        Gate::authorize('assign', $case);

        $validated = $request->validate(['lawyer_id' => ['required', 'integer', 'exists:users,id']]);

        try {
            $this->claims->assignLawyer($case, User::query()->findOrFail($validated['lawyer_id']), $request->user());
        } catch (DomainException $e) {
            return $this->fail($request, $e->getMessage());
        }

        return $this->respond($request, __('Lawyer assigned.'));
    }
}
