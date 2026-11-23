<?php

namespace Modules\Leases\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Support\Money;
use App\Support\PhoneNumber;
use App\Support\Scope\ActiveContext;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Leases\Enums\LeaseStatus;
use Modules\Leases\Enums\RentFrequency;
use Modules\Leases\Models\Lease;
use Modules\Leases\Models\LeaseDispute;
use Modules\Leases\Rules\NairaAmount;
use Modules\Leases\Services\LeaseService;

/** The school's lease register: list, add, view, edit and remove. */
class LeaseController extends Controller
{
    public function __construct(private readonly LeaseService $leases) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Lease::class);

        $filter = (string) $request->query('filter', 'all');
        $filter = in_array($filter, ['all', 'expiring', 'disputed', 'expired'], true) ? $filter : 'all';
        $search = trim((string) $request->query('q'));

        $soon = today()->addDays(max(Lease::REMINDER_DAYS))->toDateString();

        $leases = Lease::query()
            ->with('school')
            ->search($search)
            ->when($filter === 'expiring', fn ($q) => $q->live()->whereDate('end_date', '>=', today()->toDateString())->whereDate('end_date', '<=', $soon))
            ->when($filter === 'disputed', fn ($q) => $q->where('status', LeaseStatus::Disputed->value))
            ->when($filter === 'expired', fn ($q) => $q->where('status', LeaseStatus::Expired->value))
            ->orderBy('end_date')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        return view('leases::leases.index', [
            'leases' => $leases,
            'filter' => $filter,
            'search' => $search,
            'stats' => $this->leases->register(),
            'canCreate' => Gate::allows('create', Lease::class),
        ]);
    }

    public function create(ActiveContext $context): View
    {
        Gate::authorize('create', Lease::class);

        return view('leases::leases.form', $this->formData($context, new Lease(['rent_frequency' => RentFrequency::Yearly])));
    }

    public function store(Request $request, ActiveContext $context): RedirectResponse
    {
        Gate::authorize('create', Lease::class);

        $schoolId = $context->schoolId();
        $validated = $request->validate($this->rules($schoolId === null));

        // School::query() is scoped to the active role, so a foreign id is simply not found.
        $school = School::query()->find($schoolId ?? $validated['school_id']);

        if ($school === null) {
            return back()->withInput()->withErrors(['school_id' => __('Choose one of your schools.')]);
        }

        $lease = $this->leases->createLease($school, $this->payload($validated));

        return redirect()->route('leases.show', $lease)->with('status', __('Lease added to the register.'));
    }

    public function show(Lease $lease): View
    {
        Gate::authorize('view', $lease);

        $lease->load(['school', 'media', 'disputes.lawyer', 'rentPayments.recorder']);

        return view('leases::leases.show', [
            'lease' => $lease,
            'payments' => $lease->rentPayments->take(50),
            'paidKobo' => $this->leases->totalPaid($lease),
            'documents' => $lease->getMedia(Lease::VAULT),
            'canGenerate' => $this->leases->canGenerateModelLease(),
            'hasOpenDispute' => $lease->disputes->contains(fn ($d) => $d->isOpen()),
            'canOpenDispute' => Gate::allows('create', LeaseDispute::class),
        ]);
    }

    public function edit(Lease $lease, ActiveContext $context): View
    {
        Gate::authorize('update', $lease);

        return view('leases::leases.form', $this->formData($context, $lease));
    }

    public function update(Request $request, Lease $lease): RedirectResponse
    {
        Gate::authorize('update', $lease);

        $validated = $request->validate($this->rules(false) + [
            'status' => ['nullable', Rule::in(array_map(fn (LeaseStatus $s) => $s->value, LeaseStatus::manual()))],
        ]);

        $this->leases->updateLease($lease, $this->payload($validated) + ['status' => $validated['status'] ?? null]);

        return redirect()->route('leases.show', $lease)->with('status', __('Lease updated.'));
    }

    public function destroy(Lease $lease): RedirectResponse
    {
        Gate::authorize('delete', $lease);

        if ($lease->disputes()->exists()) {
            return back()->with('error', __('A lease with a dispute on record cannot be deleted. Mark it as terminated instead.'));
        }

        activity('leases')->performedOn($lease)->event('deleted')
            ->withProperties(['landlord_name' => $lease->landlord_name, 'property_address' => $lease->property_address, 'school_id' => $lease->school_id])
            ->log("Lease {$lease->getKey()} deleted");

        $lease->delete();

        return redirect()->route('leases.index')->with('status', __('Lease deleted.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(bool $needsSchool): array
    {
        $rules = [
            'landlord_name' => ['required', 'string', 'max:255'],
            'landlord_phone' => ['nullable', 'string', 'max:30', function (string $attribute, mixed $value, Closure $fail) {
                if (filled($value) && PhoneNumber::normalize((string) $value) === null) {
                    $fail(__('Enter a valid Nigerian phone number.'));
                }
            }],
            'property_address' => ['required', 'string', 'max:2000'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'rent' => ['required', new NairaAmount],
            'rent_frequency' => ['required', Rule::enum(RentFrequency::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];

        if ($needsSchool) {
            $rules['school_id'] = ['required', 'integer'];
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function payload(array $validated): array
    {
        return [
            'landlord_name' => $validated['landlord_name'],
            'landlord_phone' => $validated['landlord_phone'] ?? null,
            'property_address' => $validated['property_address'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'rent_kobo' => (int) Money::toKobo($validated['rent']),
            'rent_frequency' => $validated['rent_frequency'],
            'notes' => $validated['notes'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(ActiveContext $context, Lease $lease): array
    {
        $school = $context->schoolId() ? School::query()->find($context->schoolId()) : null;

        return [
            'lease' => $lease,
            'school' => $school,
            'schools' => $school || $lease->exists ? collect() : School::query()->orderBy('name')->limit(500)->pluck('name', 'id'),
            'frequencies' => collect(RentFrequency::cases())->mapWithKeys(fn (RentFrequency $f) => [$f->value => $f->label()])->all(),
            'statuses' => collect(LeaseStatus::manual())->mapWithKeys(fn (LeaseStatus $s) => [$s->value => $s->label()])->all(),
        ];
    }
}
