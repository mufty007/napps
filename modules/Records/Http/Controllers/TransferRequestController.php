<?php

namespace Modules\Records\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Support\Money;
use App\Support\Scope\ActiveContext;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Records\Enums\TransferStatus;
use Modules\Records\Http\Controllers\Concerns\ChoosesSchool;
use Modules\Records\Models\TransferRequest;
use Modules\Records\Services\TransferService;

/**
 * Transfer clearance: the school a pupil is moving to asks, the school that
 * holds the record answers within five working days.
 */
class TransferRequestController extends Controller
{
    use ChoosesSchool;

    public function __construct(private readonly TransferService $transfers) {}

    public function index(Request $request, ActiveContext $context): View
    {
        Gate::authorize('viewAny', TransferRequest::class);

        $box = $request->query('box') === 'outgoing' ? 'outgoing' : 'incoming';

        $visible = fn () => TransferRequest::query()->visibleTo($context);
        $inBox = fn () => $box === 'outgoing'
            ? $visible()->askedBy($context)
            : $visible()->heldBy($context);

        $requests = $inBox()
            ->with(['student', 'fromSchool', 'toSchool'])
            ->orderByRaw("case when status = 'pending' then 0 else 1 end")
            ->orderBy('sla_due_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('records::transfers.index', [
            'requests' => $requests,
            'box' => $box,
            'counts' => [
                'incoming' => $visible()->heldBy($context)->pending()->count(),
                'outgoing' => $visible()->askedBy($context)->pending()->count(),
            ],
            'sla' => TransferService::SLA_WORKING_DAYS,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', TransferRequest::class);

        return view('records::transfers.create', ['sla' => TransferService::SLA_WORKING_DAYS]);
    }

    public function store(Request $request, ActiveContext $context): RedirectResponse
    {
        Gate::authorize('create', TransferRequest::class);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'guardian_phone' => ['required', 'string', 'max:30', $this->phoneRule()],
        ]);

        $asking = $context->schoolId() ? School::query()->find($context->schoolId()) : null;

        $student = $this->transfers->locate(
            $validated['first_name'],
            $validated['last_name'],
            $validated['guardian_phone'],
            $asking ? [$asking->getKey()] : [],
        );

        // Same answer whether the name, the phone or the school is wrong: nothing is revealed.
        if ($student === null) {
            return back()->withInput()->withErrors([
                'first_name' => __('No pupil on another school\'s register matches these details. Check the spelling and the guardian\'s phone number.'),
            ]);
        }

        try {
            $transfer = $this->transfers->request($request->user(), $student, $asking);
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('records.transfers.show', $transfer)
            ->with('status', __('Clearance requested. The school has :days working days to reply.', ['days' => TransferService::SLA_WORKING_DAYS]));
    }

    public function show(TransferRequest $transfer, ActiveContext $context): View
    {
        Gate::authorize('view', $transfer);

        $transfer->load(['student', 'fromSchool', 'toSchool', 'requester', 'responder']);

        $canRespond = Gate::allows('respond', $transfer);

        return view('records::transfers.show', [
            'transfer' => $transfer,
            'canRespond' => $canRespond,
            'canCancel' => Gate::allows('cancel', $transfer),
            // Only the school that holds the record sees the pupil's details.
            'showPupilDetails' => $canRespond || TransferRequest::query()->heldBy($context)->whereKey($transfer->getKey())->exists(),
            'suggested' => $canRespond ? $this->transfers->suggestedOutstanding($transfer) : 0,
        ]);
    }

    public function respond(Request $request, TransferRequest $transfer): RedirectResponse
    {
        Gate::authorize('respond', $transfer);

        $decision = TransferStatus::tryFrom((string) $request->input('decision'));

        $validated = $request->validate([
            'decision' => ['required', Rule::in([TransferStatus::Cleared->value, TransferStatus::Blocked->value])],
            'response_notes' => [Rule::requiredIf($decision === TransferStatus::Blocked), 'nullable', 'string', 'max:2000'],
            'outstanding' => ['nullable', function (string $attribute, mixed $value, \Closure $fail) {
                if (filled($value) && Money::toKobo((string) $value) === null) {
                    $fail(__('Enter an amount in naira, e.g. 45000.'));
                }
            }],
        ], [
            'response_notes.required' => __('Say why clearance is blocked, so the other school can tell the family.'),
        ]);

        try {
            $this->transfers->respond(
                $transfer,
                $request->user(),
                TransferStatus::from($validated['decision']),
                $validated['response_notes'] ?? null,
                (int) Money::toKobo($validated['outstanding'] ?? null),
            );
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('records.transfers.show', $transfer)->with('status', __('Reply sent.'));
    }

    public function cancel(Request $request, TransferRequest $transfer): RedirectResponse
    {
        Gate::authorize('cancel', $transfer);

        try {
            $this->transfers->cancel($transfer, $request->user());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('records.transfers.index', ['box' => 'outgoing'])->with('status', __('Request cancelled.'));
    }
}
