<?php

namespace Modules\Community\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Community\Models\Post;
use Modules\Community\Models\Reaction;
use Modules\Community\Models\Space;
use Modules\Community\Notifications\GroupMembershipUpdate;
use Modules\Community\Services\SpaceDirectory;

/**
 * Groups (PRD): automatic chapter/state/national spaces, interest groups
 * anyone can create (open, request-to-join or invite-only), and private,
 * invite-only committee groups that executives create.
 */
class GroupController extends Controller
{
    public function index(Request $request, SpaceDirectory $directory): View
    {
        $user = $request->user();
        $mine = $directory->forViewer();

        $memberships = Space::query()
            ->whereIn('type', ['interest', 'committee'])
            ->whereHas('members', fn ($q) => $q->where('users.id', $user->id)->whereIn('community_space_members.status', ['pending', 'invited']))
            ->with(['members' => fn ($q) => $q->where('users.id', $user->id)])
            ->get();

        return view('community::groups.index', [
            'automatic' => $mine->filter(fn (Space $s) => $s->isAutomatic())->values(),
            'groups' => Space::query()->whereKey($mine->filter(fn (Space $s) => $s->isGroup())->pluck('id'))->withCount('activeMembers')->orderBy('name')->get(),
            'invitations' => $memberships->filter(fn (Space $s) => $s->members->first()?->getRelation('pivot')->getAttribute('status') === 'invited')->values(),
            'requested' => $memberships->filter(fn (Space $s) => $s->members->first()?->getRelation('pivot')->getAttribute('status') === 'pending')->values(),
            'discover' => Space::query()
                ->where('type', 'interest')
                ->whereIn('privacy', ['open', 'request'])
                ->whereNotIn('id', $mine->pluck('id')->merge($memberships->pluck('id')))
                ->withCount('activeMembers')
                ->orderByDesc('active_members_count')
                ->limit(30)
                ->get(),
            'canCreateCommittee' => $user->can('org.view'),
        ]);
    }

    public function store(Request $request, SpaceDirectory $directory): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', Rule::in(['interest', 'committee'])],
            'privacy' => ['required', Rule::in(['open', 'request', 'invite'])],
        ]);

        // Committees (executives, legal, finance) are private and invite-only.
        $committee = $validated['type'] === 'committee';
        abort_if($committee && ! $request->user()->can('org.view'), 403);

        $group = Space::query()->create([
            'type' => $validated['type'],
            'name' => trim($validated['name']),
            'slug' => Str::slug($validated['name']).'-'.Str::lower(Str::random(4)),
            'privacy' => $committee ? 'invite' : $validated['privacy'],
            'description' => $validated['description'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        $directory->syncMembership($request->user(), $group, 'admin');

        return redirect()->route('community.groups.show', $group)->with('status', __('Group created. Invite members from the Members tab.'));
    }

    public function show(Request $request, Space $group, SpaceDirectory $directory): View|RedirectResponse
    {
        $user = $request->user();

        // Chapter / state / national spaces live in the feed.
        if ($group->isAutomatic()) {
            abort_unless($directory->canView($user, $group), 404);

            return redirect()->route('community.feed', ['space' => $group->id]);
        }

        $status = $directory->membershipStatus($user, $group);
        $canSee = $directory->canView($user, $group);

        // Invite-only groups and committees stay invisible to outsiders.
        abort_unless($canSee || $status !== null || $group->privacy === 'request', 404);

        $isAdmin = $directory->isGroupAdmin($user, $group) || $directory->canModerate($user, $group);
        $tab = $request->query('tab') === 'members' && $canSee ? 'members' : 'posts';

        $posts = $canSee && $tab === 'posts'
            ? Post::query()->visible()->where('space_id', $group->id)->with(['author', 'poll.options'])->withCount(['comments', 'reactions'])->orderByDesc('pinned')->latest('id')->paginate(15)
            : null;

        return view('community::groups.show', [
            'group' => $group->loadCount('activeMembers'),
            'status' => $status,
            'canSee' => $canSee,
            'canPost' => $directory->canPost($user, $group),
            'isAdmin' => $isAdmin,
            'tab' => $tab,
            'posts' => $posts,
            'likedIds' => $posts ? Reaction::query()->where('user_id', $user->id)->where('reactable_type', (new Post)->getMorphClass())->whereIn('reactable_id', $posts->getCollection()->modelKeys())->pluck('reactable_id')->map(fn ($id) => (int) $id)->all() : [],
            'members' => $tab === 'members' ? $group->members()->wherePivot('status', 'active')->orderByPivot('role')->orderBy('name')->get() : collect(),
            'pending' => $tab === 'members' && $isAdmin ? $group->members()->wherePivot('status', 'pending')->get() : collect(),
            'invited' => $tab === 'members' && $isAdmin ? $group->members()->wherePivot('status', 'invited')->get() : collect(),
            'candidates' => $tab === 'members' && $isAdmin ? $directory->searchMembers((string) $request->query('find'), $user) : collect(),
            'directory' => $directory,
        ]);
    }

    public function join(Request $request, Space $group, SpaceDirectory $directory): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        abort_unless($group->type === 'interest' && $directory->hasAccess($user), 403);

        $status = $directory->membershipStatus($user, $group);

        if ($status === 'invited' || $group->privacy === 'open') {
            $directory->syncMembership($user, $group);
            $message = __('You joined :group.', ['group' => $group->name]);

            return $request->expectsJson()
                ? response()->json(['status' => $message, 'redirect' => route('community.groups.show', $group), 'joined' => true])
                : redirect()->route('community.groups.show', $group)->with('status', $message);
        }

        abort_unless($group->privacy === 'request', 403);

        if ($status === null) {
            $directory->syncMembership($user, $group, status: 'pending');
            Notification::send($this->admins($group), new GroupMembershipUpdate($group, 'requested', $user));
        }

        $message = __('Request sent. A group admin will review it.');

        return $request->expectsJson()
            ? response()->json(['status' => $message, 'pending' => true])
            : back()->with('status', $message);
    }

    public function acceptInvite(Request $request, Space $group, SpaceDirectory $directory): RedirectResponse
    {
        abort_unless($directory->membershipStatus($request->user(), $group) === 'invited', 404);

        $directory->syncMembership($request->user(), $group);

        return redirect()->route('community.groups.show', $group)->with('status', __('You joined :group.', ['group' => $group->name]));
    }

    public function leave(Request $request, Space $group): RedirectResponse
    {
        $user = $request->user();
        abort_unless($group->isGroup(), 404);

        $admins = $group->members()->wherePivot('role', 'admin')->wherePivot('status', 'active')->pluck('users.id');
        $others = $group->members()->whereKeyNot($user->id)->wherePivot('status', 'active')->count();

        if ($admins->count() === 1 && $admins->first() === $user->id && $others > 0) {
            return back()->with('error', __('Make another member an admin before you leave.'));
        }

        $group->members()->detach($user->id);

        return redirect()->route('community.groups.index')->with('status', __('You left :group.', ['group' => $group->name]));
    }

    public function respond(Request $request, Space $group, User $member, SpaceDirectory $directory): RedirectResponse
    {
        $this->authorizeAdmin($request, $group, $directory);
        abort_unless($directory->membershipStatus($member, $group) === 'pending', 404);

        if ($request->input('decision') === 'approve') {
            $directory->syncMembership($member, $group);
            $member->notify(new GroupMembershipUpdate($group, 'approved', $request->user()));

            return back()->with('status', __(':name is now a member.', ['name' => $member->name]));
        }

        $group->members()->detach($member->id);

        return back()->with('status', __('Request declined.'));
    }

    public function invite(Request $request, Space $group, SpaceDirectory $directory): RedirectResponse
    {
        $this->authorizeAdmin($request, $group, $directory);

        $member = User::query()->findOrFail($request->integer('user_id'));
        abort_unless($directory->hasAccess($member), 422);

        if ($directory->membershipStatus($member, $group) === null) {
            $directory->syncMembership($member, $group, status: 'invited');
            $member->notify(new GroupMembershipUpdate($group, 'invited', $request->user()));
        }

        return redirect()->route('community.groups.show', [$group, 'tab' => 'members'])->with('status', __('Invitation sent to :name.', ['name' => $member->name]));
    }

    public function removeMember(Request $request, Space $group, User $member, SpaceDirectory $directory): RedirectResponse
    {
        $this->authorizeAdmin($request, $group, $directory);
        abort_if($member->is($request->user()), 422);

        $group->members()->detach($member->id);

        return back()->with('status', __(':name was removed from the group.', ['name' => $member->name]));
    }

    public function makeAdmin(Request $request, Space $group, User $member, SpaceDirectory $directory): RedirectResponse
    {
        $this->authorizeAdmin($request, $group, $directory);
        abort_unless($directory->membershipStatus($member, $group) === 'active', 404);

        $group->members()->updateExistingPivot($member->id, ['role' => 'admin']);

        return back()->with('status', __(':name is now a group admin.', ['name' => $member->name]));
    }

    private function authorizeAdmin(Request $request, Space $group, SpaceDirectory $directory): void
    {
        abort_unless($group->isGroup() && ($directory->isGroupAdmin($request->user(), $group) || $directory->canModerate($request->user(), $group)), 403);
    }

    /**
     * @return Collection<int, User>
     */
    private function admins(Space $group): Collection
    {
        return $group->members()->wherePivot('role', 'admin')->wherePivot('status', 'active')->get();
    }
}
