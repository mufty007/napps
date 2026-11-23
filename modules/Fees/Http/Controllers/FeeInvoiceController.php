<?php

namespace Modules\Fees\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Support\PhoneNumber;
use App\Support\Scope\ActiveContext;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Fees\Enums\FeePaymentMethod;
use Modules\Fees\Models\FeeInvoice;
use Modules\Fees\Models\FeeStructure;
use Modules\Fees\Services\FeeInvoiceService;

/**
 * FEE-1/FEE-2: the school's invoices. The list has All / Unpaid / Overdue /
 * Paid tabs that load in place (ajax requests get just the results block).
 */
class FeeInvoiceController extends Controller
{
    private const TABS = ['all', 'unpaid', 'overdue', 'paid'];

    private const MAX_BULK = 500;

    public function __construct(private readonly FeeInvoiceService $invoices) {}

    public function index(Request $request, ActiveContext $context): View|Response
    {
        Gate::authorize('viewAny', FeeInvoice::class);

        $status = in_array($request->query('status'), self::TABS, true) ? (string) $request->query('status') : 'all';
        $search = trim((string) $request->query('q'));

        $visible = fn () => FeeInvoice::query();
        $inTab = fn (string $tab) => match ($tab) {
            'unpaid' => $visible()->unpaid(),
            'overdue' => $visible()->overdue(),
            'paid' => $visible()->settled(),
            default => $visible(),
        };

        $invoices = $inTab($status)
            ->search($search)
            ->with($context->schoolId() === null ? ['school', 'guardian'] : ['guardian'])
            ->orderByRaw("case status when 'overdue' then 0 when 'partial' then 1 when 'issued' then 2 when 'draft' then 3 else 4 end")
            ->orderBy('due_date')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        $data = [
            'invoices' => $invoices,
            'status' => $status,
            'search' => $search,
            'counts' => [
                'all' => $visible()->count(),
                'unpaid' => $visible()->unpaid()->count(),
                'overdue' => $visible()->overdue()->count(),
                'paid' => $visible()->settled()->count(),
            ],
            'owedKobo' => (int) $visible()->unpaid()->toBase()->sum(DB::raw('total_kobo - paid_kobo')),
            'showSchool' => $context->schoolId() === null,
            'canCreate' => Gate::allows('create', FeeInvoice::class),
        ];

        if ($request->ajax()) {
            return response()->view('fees::invoices.partials.results', $data);
        }

        return view('fees::invoices.index', $data + [
            'canManageStructures' => Gate::allows('create', FeeStructure::class),
            'ageing' => $this->invoices->ageing(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', FeeInvoice::class);

        return view('fees::invoices.create', [
            'structures' => FeeStructure::query()->with('items')->where('active', true)->orderBy('name')->get(),
            'canManageStructures' => Gate::allows('create', FeeStructure::class),
        ]);
    }

    /**
     * Bulk: one invoice per child from a pasted list, on one fee structure.
     */
    public function store(Request $request, ActiveContext $context): RedirectResponse
    {
        Gate::authorize('create', FeeInvoice::class);

        $validated = $request->validate([
            'fee_structure_id' => ['required', 'integer'],
            'due_date' => ['required', 'date'],
            'students' => ['required', 'string', 'max:50000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'issue' => ['nullable', 'boolean'],
        ]);

        // FeeStructure::query() is school-scoped, so another school's id is simply not found.
        $structure = FeeStructure::query()->with('items')->where('active', true)->find($validated['fee_structure_id']);

        if ($structure === null) {
            throw ValidationException::withMessages(['fee_structure_id' => __('Choose one of your active fee structures.')]);
        }

        $students = $this->parseStudents($validated['students']);
        $school = School::query()->findOrFail($context->schoolId());

        try {
            $result = $this->invoices->bulkCreate(
                school: $school,
                structure: $structure,
                dueDate: CarbonImmutable::parse($validated['due_date']),
                students: $students,
                notes: $validated['notes'] ?? null,
                issue: (bool) ($validated['issue'] ?? false),
            );
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $created = $result['created']->count();
        $message = ($validated['issue'] ?? false)
            ? trans_choice(':count invoice issued and the families notified.|:count invoices issued and the families notified.', $created, ['count' => $created])
            : trans_choice(':count draft invoice created.|:count draft invoices created.', $created, ['count' => $created]);

        if ($result['skipped'] > 0) {
            $message .= ' '.trans_choice(':count child already had an invoice on this structure and was skipped.|:count children already had an invoice on this structure and were skipped.', $result['skipped'], ['count' => $result['skipped']]);
        }

        return redirect()->route('fees.invoices.index')->with('status', $message);
    }

    public function show(FeeInvoice $invoice): View
    {
        Gate::authorize('view', $invoice);

        $invoice->load(['guardian', 'structure', 'payments.recorder', 'reminders']);

        return view('fees::invoices.show', [
            'invoice' => $invoice,
            'school' => School::withoutGlobalScopes()->find($invoice->school_id),
            'canManage' => Gate::allows('manage', $invoice),
            'canRecord' => Gate::allows('recordPayment', $invoice) && $invoice->isOpen(),
            'canEscalate' => Gate::allows('escalate', $invoice) && $this->invoices->canEscalate($invoice),
            'claimsAvailable' => $this->invoices->claimsAvailable(),
            'methods' => collect(FeePaymentMethod::offline())->mapWithKeys(fn (FeePaymentMethod $m) => [$m->value => $m->label()])->all(),
            'remindersAllowed' => $this->invoices->remindersAllowed($invoice),
        ]);
    }

    public function issue(FeeInvoice $invoice): RedirectResponse
    {
        Gate::authorize('manage', $invoice);

        try {
            $this->invoices->issue($invoice);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('Invoice issued. The family has been notified.'));
    }

    public function void(FeeInvoice $invoice): RedirectResponse
    {
        Gate::authorize('manage', $invoice);

        try {
            $this->invoices->void($invoice);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('Invoice voided.'));
    }

    public function remind(FeeInvoice $invoice): RedirectResponse
    {
        Gate::authorize('manage', $invoice);

        $invoice->loadMissing('guardian');

        return $this->invoices->remindNow($invoice)
            ? back()->with('status', __('Reminder sent.'))
            : back()->with('error', __('No reminder was sent: the invoice is not open, the family has no phone number, or they opted out of reminders.'));
    }

    /**
     * Remind every overdue family, skipping anyone reminded in the last three days.
     */
    public function remindOverdue(): RedirectResponse
    {
        Gate::authorize('create', FeeInvoice::class);

        $sent = 0;
        $skipped = 0;

        FeeInvoice::query()->overdue()->with(['guardian', 'reminders'])->each(function (FeeInvoice $invoice) use (&$sent, &$skipped) {
            $recent = $invoice->reminders->first()?->sent_at?->gt(now()->subDays(3));

            if (! $recent && $this->invoices->remindNow($invoice)) {
                $sent++;
            } else {
                $skipped++;
            }
        });

        return back()->with('status', __(':sent reminders sent, :skipped skipped (reminded recently, no phone number or opted out).', ['sent' => $sent, 'skipped' => $skipped]));
    }

    public function escalate(Request $request, FeeInvoice $invoice): RedirectResponse
    {
        Gate::authorize('escalate', $invoice);

        try {
            $reference = $this->invoices->escalateToClaim($invoice, $request->user());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('Claim :ref opened for the balance.', ['ref' => $reference]));
    }

    /**
     * One child per line: "Name, Guardian name, Guardian phone".
     *
     * @return list<array{student_name: string, guardian_name: ?string, guardian_phone: ?string}>
     */
    private function parseStudents(string $text): array
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', $text) ?: []), fn (string $l) => $l !== ''));

        if ($lines === []) {
            throw ValidationException::withMessages(['students' => __('Add at least one child.')]);
        }

        if (count($lines) > self::MAX_BULK) {
            throw ValidationException::withMessages(['students' => __('Add at most :max children at a time.', ['max' => self::MAX_BULK])]);
        }

        $rows = [];

        foreach ($lines as $i => $line) {
            $cells = array_map('trim', str_getcsv(str_replace("\t", ',', $line)));
            $phone = ($cells[2] ?? '') !== '' ? $cells[2] : null;

            if ($phone !== null && PhoneNumber::normalize($phone) === null) {
                throw ValidationException::withMessages(['students' => __('Line :line: :phone is not a valid Nigerian phone number.', ['line' => $i + 1, 'phone' => $phone])]);
            }

            if (($cells[0] ?? '') === '' || mb_strlen($cells[0]) > 255) {
                throw ValidationException::withMessages(['students' => __('Line :line: start each line with the child\'s name.', ['line' => $i + 1])]);
            }

            $rows[] = [
                'student_name' => $cells[0],
                'guardian_name' => ($cells[1] ?? '') !== '' ? $cells[1] : null,
                'guardian_phone' => $phone,
            ];
        }

        return $rows;
    }
}
