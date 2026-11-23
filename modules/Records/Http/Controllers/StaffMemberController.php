<?php

namespace Modules\Records\Http\Controllers;

use App\Enums\Role;
use App\Enums\ScopeLevel;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\PhoneNumber;
use App\Support\Scope\ActiveContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Records\Http\Controllers\Concerns\ChoosesSchool;
use Modules\Records\Models\StaffMember;

/** The school's staff register (not platform accounts: most teachers never sign in). */
class StaffMemberController extends Controller
{
    use ChoosesSchool;

    public function index(Request $request, ActiveContext $context): View
    {
        Gate::authorize('viewAny', StaffMember::class);

        $tab = $request->query('tab') === 'former' ? 'former' : 'current';
        $search = trim((string) $request->query('q'));

        $members = StaffMember::query()
            ->with('school')
            ->when($tab === 'former', fn ($q) => $q->former(), fn ($q) => $q->current())
            ->search($search)
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('records::staff.index', [
            'members' => $members,
            'tab' => $tab,
            'search' => $search,
            'counts' => ['current' => StaffMember::query()->current()->count(), 'former' => StaffMember::query()->former()->count()],
            'showSchool' => $context->schoolId() === null,
            'canAdd' => Gate::allows('create', StaffMember::class),
        ]);
    }

    public function create(ActiveContext $context): View
    {
        Gate::authorize('create', StaffMember::class);

        return view('records::staff.form', [
            'member' => new StaffMember(['started_at' => today()]),
            'schools' => $this->schoolOptions($context),
        ]);
    }

    public function store(Request $request, ActiveContext $context): RedirectResponse
    {
        Gate::authorize('create', StaffMember::class);

        $validated = $request->validate($this->rules($request) + $this->schoolRules($context));

        $school = $this->chosenSchool($context, $validated);

        if ($school === null) {
            return back()->withInput()->withErrors(['school_id' => __('Choose one of your schools.')]);
        }

        $member = StaffMember::query()->create($this->attributes($validated, $school->getKey()) + ['school_id' => $school->getKey()]);

        return redirect()->route('records.staff.index')->with('status', __(':name added to the staff register.', ['name' => $member->name]));
    }

    public function edit(StaffMember $member): View
    {
        Gate::authorize('update', $member);

        return view('records::staff.form', ['member' => $member, 'schools' => collect()]);
    }

    public function update(Request $request, StaffMember $member): RedirectResponse
    {
        Gate::authorize('update', $member);

        $validated = $request->validate($this->rules($request));

        $member->update($this->attributes($validated, $member->school_id));

        return redirect()->route('records.staff.index')->with('status', __('Staff record updated.'));
    }

    public function destroy(StaffMember $member): RedirectResponse
    {
        Gate::authorize('delete', $member);

        $name = $member->name;
        $member->delete();

        return redirect()->route('records.staff.index')->with('status', __(':name removed from the register.', ['name' => $name]));
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(Request $request): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'role_title' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30', $this->phoneRule()],
            'started_at' => ['nullable', 'date', 'before_or_equal:today'],
            'left_at' => array_values(array_filter(['nullable', 'date', $request->filled('started_at') ? 'after_or_equal:started_at' : null])),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function attributes(array $validated, int $schoolId): array
    {
        $phone = PhoneNumber::normalize($validated['phone'] ?? null);

        return [
            'name' => trim($validated['name']),
            'role_title' => trim($validated['role_title']),
            'phone' => $phone,
            'started_at' => $validated['started_at'] ?? null,
            'left_at' => $validated['left_at'] ?? null,
            // Link to the person's account when they already have a staff role at this school.
            'user_id' => $phone
                ? User::query()->holdingRole(Role::Staff, ScopeLevel::School, $schoolId)->where('phone', $phone)->value('id')
                : null,
        ];
    }
}
