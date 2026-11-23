<?php

namespace Modules\Fees\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\School;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Fees\Models\FeeAgreement;
use Modules\Fees\Models\FeeInvoice;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * FEE-4: the fee agreement a parent signs with a school, with their consent
 * to SMS reminders and to the NAPPS debtor check. Parents sign (and re-read)
 * their own; the school's staff can read what was signed with them.
 */
class FeeAgreementController extends Controller
{
    /** The school's signed agreements (bursar, proprietor). */
    public function index(): View
    {
        Gate::authorize('viewAny', FeeAgreement::class);

        return view('fees::agreements.index', [
            'agreements' => FeeAgreement::query()->with('guardian')->latest('signed_at')->paginate(25),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', FeeAgreement::class);

        $invoices = FeeInvoice::withoutGlobalScopes()
            ->where('guardian_user_id', $request->user()->getKey())
            ->get(['school_id', 'student_name']);

        $schools = School::withoutGlobalScopes()->whereIn('id', $invoices->pluck('school_id')->unique())->orderBy('name')->pluck('name', 'id');

        return view('fees::agreements.create', [
            'schools' => $schools,
            'students' => $invoices->groupBy('school_id')->map(fn ($rows) => $rows->pluck('student_name')->unique()->values()->all()),
            'selectedSchool' => $request->integer('school') ?: $schools->keys()->first(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', FeeAgreement::class);

        $validated = $request->validate([
            'school_id' => ['required', 'integer'],
            'student_name' => ['required', 'string', 'max:255'],
            'signature' => ['required', 'string', 'max:255'],
            'accept' => ['accepted'],
            'consent_reminders' => ['nullable', 'boolean'],
            'consent_debtor_check' => ['nullable', 'boolean'],
        ], [
            'accept.accepted' => __('Tick the box to confirm you have read and accept the agreement.'),
        ]);

        // A parent can sign only with a school that has billed them.
        $billed = FeeInvoice::withoutGlobalScopes()
            ->where('guardian_user_id', $request->user()->getKey())
            ->where('school_id', $validated['school_id'])
            ->exists();

        if (! $billed) {
            throw ValidationException::withMessages(['school_id' => __('Choose a school that has sent you an invoice.')]);
        }

        $school = School::withoutGlobalScopes()->findOrFail($validated['school_id']);

        $agreement = DB::transaction(function () use ($request, $validated, $school) {
            $agreement = FeeAgreement::withoutGlobalScopes()->create([
                'school_id' => $school->getKey(),
                'guardian_user_id' => $request->user()->getKey(),
                'student_name' => trim($validated['student_name']),
                'signed_at' => now(),
                'consent_reminders' => (bool) ($validated['consent_reminders'] ?? false),
                'consent_debtor_check' => (bool) ($validated['consent_debtor_check'] ?? false),
            ]);

            $path = sprintf('fees/agreements/%d-%s.pdf', $agreement->getKey(), now()->format('YmdHis'));
            Storage::disk($this->disk())->put($path, $this->render($agreement, $school, $validated['signature']));
            $agreement->forceFill(['pdf_path' => $path])->save();

            return $agreement;
        });

        return redirect()->route('fees.parent.index')->with('status', __('Agreement signed for :student. A copy is saved to your account.', ['student' => $agreement->student_name]));
    }

    public function show(Request $request, FeeAgreement $agreement): StreamedResponse
    {
        Gate::authorize('view', $agreement);

        $disk = Storage::disk($this->disk());

        abort_if($agreement->pdf_path === null || ! $disk->exists($agreement->pdf_path), 404);

        return $disk->response($agreement->pdf_path, 'fee-agreement-'.$agreement->getKey().'.pdf', ['Content-Type' => 'application/pdf']);
    }

    private function render(FeeAgreement $agreement, School $school, string $signature): string
    {
        $agreement->loadMissing('guardian');

        return Pdf::loadView('fees::pdf.agreement', [
            'agreement' => $agreement,
            'school' => $school,
            'signature' => $signature,
        ])->setPaper('a4')->output();
    }

    private function disk(): string
    {
        return (string) config('napps.fees.documents_disk', config('napps.membership.documents_disk', 'local'));
    }
}
