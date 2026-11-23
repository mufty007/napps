<?php

namespace Modules\Records\Http\Controllers;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Money;
use App\Support\PhoneNumber;
use App\Support\Scope\ActiveContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Records\Enums\StudentStatus;
use Modules\Records\Http\Controllers\Concerns\ChoosesSchool;
use Modules\Records\Models\Student;
use Modules\Records\Models\TransferRequest;

class StudentController extends Controller
{
    use ChoosesSchool;

    /**
     * Register with status tabs, class filter and search. Ajax requests (tabs,
     * search box, pager) get just the results block.
     */
    public function index(Request $request, ActiveContext $context): View|Response
    {
        Gate::authorize('viewAny', Student::class);

        $status = StudentStatus::tryFrom((string) $request->query('status')) ?? StudentStatus::Active;
        $class = trim((string) $request->query('class'));
        $search = trim((string) $request->query('q'));

        $students = Student::query()
            ->with('school')
            ->where('status', $status->value)
            ->when($class !== '', fn ($q) => $q->where('class_name', $class))
            ->search($search)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(20)
            ->withQueryString();

        $data = [
            'students' => $students,
            'status' => $status,
            'class' => $class,
            'search' => $search,
            'counts' => Student::query()->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'showSchool' => $context->schoolId() === null,
            'canEnrol' => Gate::allows('create', Student::class),
        ];

        if ($request->ajax()) {
            return response()->view('records::students.partials.results', $data);
        }

        return view('records::students.index', $data + [
            'classes' => Student::query()->whereNotNull('class_name')->distinct()->orderBy('class_name')->pluck('class_name', 'class_name'),
        ]);
    }

    public function create(ActiveContext $context): View
    {
        Gate::authorize('create', Student::class);

        return view('records::students.form', [
            'student' => new Student(['status' => StudentStatus::Active, 'enrolled_at' => today()]),
            'schools' => $this->schoolOptions($context),
            'canSetBalance' => Gate::allows('setBalance', Student::class),
        ]);
    }

    public function store(Request $request, ActiveContext $context): RedirectResponse
    {
        Gate::authorize('create', Student::class);

        $validated = $request->validate($this->rules($request, null) + $this->schoolRules($context));

        $school = $this->chosenSchool($context, $validated);

        if ($school === null) {
            return back()->withInput()->withErrors(['school_id' => __('Choose one of your schools.')]);
        }

        // Admission numbers are unique within the school; check again now the school is known.
        if (filled($validated['admission_no'] ?? null) && $this->admissionTaken($school->getKey(), $validated['admission_no'])) {
            return back()->withInput()->withErrors(['admission_no' => __('That admission number is already used at this school.')]);
        }

        $student = Student::query()->create($this->attributes($validated, $request) + [
            'school_id' => $school->getKey(),
            'status' => StudentStatus::Active,
        ]);

        return redirect()->route('records.students.show', $student)
            ->with('status', __(':name enrolled.', ['name' => $student->fullName()]));
    }

    public function show(Student $student): View
    {
        Gate::authorize('view', $student);

        $student->load(['school', 'guardian', 'media']);

        $canSeeTransfers = Gate::allows('viewAny', TransferRequest::class);

        return view('records::students.show', [
            'student' => $student,
            'documents' => collect(Student::DOCUMENTS)->map(fn (string $label, string $collection) => [
                'label' => $label,
                'files' => $student->getMedia($collection),
            ]),
            'transfers' => $canSeeTransfers
                ? $student->transferRequests()->with(['toSchool', 'fromSchool'])->limit(10)->get()
                : collect(),
        ]);
    }

    public function edit(Student $student): View
    {
        Gate::authorize('update', $student);

        return view('records::students.form', [
            'student' => $student,
            'schools' => collect(),
            'canSetBalance' => Gate::allows('setBalance', Student::class),
        ]);
    }

    public function update(Request $request, Student $student): RedirectResponse
    {
        Gate::authorize('update', $student);

        $validated = $request->validate($this->rules($request, $student) + [
            'status' => ['required', Rule::enum(StudentStatus::class)],
        ]);

        if (filled($validated['admission_no'] ?? null) && $this->admissionTaken($student->school_id, $validated['admission_no'], $student->getKey())) {
            return back()->withInput()->withErrors(['admission_no' => __('That admission number is already used at this school.')]);
        }

        $status = StudentStatus::from($validated['status']);

        $student->update($this->attributes($validated, $request, $student) + [
            'status' => $status,
            // Leaving needs a date; coming back clears it.
            'exited_at' => $status === StudentStatus::Active ? null : ($validated['exited_at'] ?? $student->exited_at ?? today()),
        ]);

        return redirect()->route('records.students.show', $student)->with('status', __('Record updated.'));
    }

    /** Archives the record (soft delete); transfer history and the audit trail stay. */
    public function destroy(Student $student): RedirectResponse
    {
        Gate::authorize('delete', $student);

        $name = $student->fullName();
        $student->delete();

        return redirect()->route('records.students.index')->with('status', __(':name archived.', ['name' => $name]));
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(Request $request, ?Student $student): array
    {
        $balance = fn (string $attribute, mixed $value, \Closure $fail) => filled($value) && Money::toKobo((string) $value) === null
            ? $fail(__('Enter an amount in naira, e.g. 45000.'))
            : null;

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'admission_no' => ['nullable', 'string', 'max:40'],
            'class_name' => ['nullable', 'string', 'max:60'],
            'guardian_name' => ['required', 'string', 'max:150'],
            'guardian_phone' => [
                Rule::requiredIf($request->boolean('debtor_check_consent')),
                'nullable', 'string', 'max:30', $this->phoneRule(),
            ],
            'enrolled_at' => ['nullable', 'date', 'before_or_equal:today'],
            'exited_at' => ['nullable', 'date'],
            'debtor_check_consent' => ['boolean'],
            'outstanding' => ['nullable', $balance],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function attributes(array $validated, Request $request, ?Student $student = null): array
    {
        $phone = PhoneNumber::normalize($validated['guardian_phone'] ?? null);

        $attributes = [
            'first_name' => trim($validated['first_name']),
            'last_name' => trim($validated['last_name']),
            'admission_no' => filled($validated['admission_no'] ?? null) ? trim($validated['admission_no']) : null,
            'class_name' => filled($validated['class_name'] ?? null) ? trim($validated['class_name']) : null,
            'guardian_name' => trim($validated['guardian_name']),
            'guardian_phone' => $phone,
            // Parents are enrolled by chapter executives; link when this phone already has an account.
            'guardian_user_id' => $phone
                ? User::query()->holdingRole(Role::Parent)->where('phone', $phone)->value('id')
                : null,
            'enrolled_at' => $validated['enrolled_at'] ?? $student?->enrolled_at ?? today(),
            'debtor_check_consent' => $phone !== null && $request->boolean('debtor_check_consent'),
        ];

        if (Gate::allows('setBalance', Student::class) && array_key_exists('outstanding', $validated)) {
            $attributes['outstanding_kobo'] = Money::toKobo($validated['outstanding'] ?? null) ?? 0;
        }

        return $attributes;
    }

    private function admissionTaken(int $schoolId, string $admissionNo, ?int $ignoreId = null): bool
    {
        return Student::query()
            ->where('school_id', $schoolId)
            ->where('admission_no', trim($admissionNo))
            ->when($ignoreId, fn ($q, int $id) => $q->whereKeyNot($id))
            ->exists();
    }
}
