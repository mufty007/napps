<?php

namespace Modules\Membership\Http\Controllers;

use App\Enums\Role;
use App\Enums\ScopeLevel;
use App\Http\Controllers\Controller;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Rules\NigerianPhone;
use App\Support\PhoneNumber;
use App\Support\Scope\ActiveContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Membership\Models\ChapterParent;

/**
 * Chapter executives enrol parents. Parents cannot create their own accounts.
 */
class ChapterParentController extends Controller
{
    public function index(ActiveContext $context): View
    {
        Gate::authorize('parents.manage');

        $chapterId = $context->chapterId()
            ?? abort(403, __('Switch to a chapter role to manage parents.'));

        $parents = ChapterParent::query()
            ->with(['user', 'addedBy'])
            ->where('chapter_id', $chapterId)
            ->latest()
            ->paginate(25);

        return view('membership::parents.index', compact('parents'));
    }

    public function create(ActiveContext $context): View
    {
        Gate::authorize('parents.manage');
        abort_if($context->chapterId() === null, 403);

        return view('membership::parents.create');
    }

    public function store(Request $request, ActiveContext $context): RedirectResponse
    {
        Gate::authorize('parents.manage');

        $chapterId = $context->chapterId()
            ?? abort(403, __('Switch to a chapter role to manage parents.'));

        $request->merge(['phone_e164' => PhoneNumber::normalize($request->input('phone'))]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', new NigerianPhone],
            'phone_e164' => ['required', 'string'],
            'email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $existing = User::withTrashed()->where('phone', $validated['phone_e164'])->first();

        if ($existing?->trashed()) {
            return back()->withErrors(['phone' => __('This phone number belongs to a deleted account. Contact the secretariat.')])->withInput();
        }

        if ($existing && ChapterParent::query()->where('chapter_id', $chapterId)->where('user_id', $existing->id)->exists()) {
            return back()->withErrors(['phone' => __('This parent is already on your chapter list.')])->withInput();
        }

        $email = filled($validated['email'] ?? null) ? mb_strtolower((string) $validated['email']) : null;

        if (! $existing && $email) {
            $request->validate(['email' => [Rule::unique('users', 'email')]]);
        }

        DB::transaction(function () use ($validated, $existing, $chapterId, $request, $email) {
            if ($existing) {
                $user = $existing;
                if (blank($user->name)) {
                    $user->forceFill(['name' => $validated['name']])->save();
                }
            } else {
                $user = new User([
                    'name' => $validated['name'],
                    'phone' => $validated['phone_e164'],
                    'email' => $email,
                ]);
                $user->phone_verified_at = now();
                $user->save();
            }

            RoleAssignment::grant($user, Role::Parent, ScopeLevel::Personal, null, $request->user());

            ChapterParent::query()->firstOrCreate(
                ['chapter_id' => $chapterId, 'user_id' => $user->id],
                ['added_by' => $request->user()->id, 'notes' => $validated['notes'] ?? null],
            );
        });

        return redirect()
            ->route('membership.parents.index')
            ->with('status', __('Parent added. They can sign in with their phone number; they do not need to register.'));
    }
}
