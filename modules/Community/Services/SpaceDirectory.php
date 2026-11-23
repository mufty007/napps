<?php

namespace Modules\Community\Services;

use App\Enums\Role;
use App\Enums\ScopeLevel;
use App\Models\Chapter;
use App\Models\RoleAssignment;
use App\Models\School;
use App\Models\State;
use App\Models\User;
use App\Support\Scope\ActiveContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\Community\Models\Forum;
use Modules\Community\Models\Space;

/**
 * Who belongs where in the community, and what they may do there.
 *
 * Membership is worked out for the *person*, across every NAPPS role they
 * hold (a proprietor who is also a chapter chair belongs to both), not just
 * the active role:
 *   - chapter / state / national spaces follow their roles automatically
 *     (school roles count only for approved member schools);
 *   - interest and committee groups follow explicit memberships.
 * Parents never get community access.
 */
class SpaceDirectory
{
    /** PRD: topic forums that exist at national, state and chapter level. */
    public const DEFAULT_FORUMS = [
        ['Fee collection', 'fees', 'Getting parents to pay on time: reminders, instalments, arrears.'],
        ['Legal & claims', 'legal', 'Demand letters, mediation and small claims court.'],
        ['Landlords & facilities', 'facilities', 'Leases, renewals and premises disputes.'],
        ['Curriculum & exams', 'curriculum', 'Schemes of work, exams and results.'],
        ['Staffing', 'staffing', 'Hiring, training and keeping good teachers.'],
        ['Technology', 'technology', 'Software, devices and low-bandwidth tips.'],
        ['Government & regulation', 'regulation', 'Ministry circulars, approvals and policy changes.'],
    ];

    /** @var array<int, array<string, mixed>> */
    private array $profiles = [];

    public function __construct(private readonly ActiveContext $context) {}

    public function ensureNationalSpace(): Space
    {
        return $this->withDefaults(Space::query()->firstOrCreate(
            ['type' => 'national', 'slug' => 'napps-national'],
            [
                'name' => __('NAPPS National'),
                'privacy' => 'open',
                'description' => __('National feed for verified NAPPS members.'),
            ],
        ));
    }

    public function ensureStateSpace(State $state): Space
    {
        return $this->withDefaults(Space::query()->firstOrCreate(
            ['type' => 'state', 'state_id' => $state->id],
            [
                'name' => __(':state State', ['state' => $state->name]),
                'slug' => Str::slug($state->name).'-state-'.$state->id,
                'privacy' => 'open',
                'description' => __('Members across :state State.', ['state' => $state->name]),
            ],
        ));
    }

    public function ensureChapterSpace(Chapter $chapter): Space
    {
        return $this->withDefaults(Space::query()->firstOrCreate(
            ['type' => 'chapter', 'chapter_id' => $chapter->id],
            [
                'name' => $chapter->name,
                'slug' => Str::slug($chapter->name).'-ch'.$chapter->id,
                'privacy' => 'open',
                'description' => __('Official space for :chapter members.', ['chapter' => $chapter->name]),
                'state_id' => $chapter->state_id,
            ],
        ));
    }

    /**
     * Every automatic space gets the PRD's topic forums.
     */
    public function ensureDefaultForums(Space $space): void
    {
        if (! $space->isAutomatic() || $space->forums()->exists()) {
            return;
        }

        foreach (self::DEFAULT_FORUMS as [$name, $category, $description]) {
            Forum::query()->firstOrCreate(
                ['space_id' => $space->id, 'slug' => Str::slug($name)],
                ['name' => $name, 'category' => $category, 'description' => $description],
            );
        }
    }

    /**
     * The person's home spaces (feed sources): national, their states and
     * chapters, and the groups they are active members of.
     *
     * @return Collection<int, Space>
     */
    public function forViewer(?User $user = null): Collection
    {
        $user ??= $this->context->user();

        if (! $user) {
            return collect();
        }

        $profile = $this->profile($user);

        if (! $profile['access']) {
            return collect();
        }

        $spaces = collect([$this->ensureNationalSpace()]);

        State::query()->whereKey($profile['homeStates'])->orderBy('name')->get()
            ->each(fn (State $state) => $spaces->push($this->ensureStateSpace($state)));

        Chapter::query()->whereKey($profile['homeChapters'])->orderBy('name')->get()
            ->each(fn (Chapter $chapter) => $spaces->push($this->ensureChapterSpace($chapter)));

        $groups = Space::query()
            ->whereIn('type', ['interest', 'committee'])
            ->whereHas('members', fn ($q) => $q->where('users.id', $user->id)->where('community_space_members.status', 'active'))
            ->orderBy('name')
            ->get();

        return $spaces->merge($groups)->unique('id')->values();
    }

    public function hasAccess(User $user): bool
    {
        return $this->profile($user)['access'];
    }

    public function canView(User $user, Space $space): bool
    {
        $profile = $this->profile($user);

        if (! $profile['access']) {
            return false;
        }

        if ($space->isGroup()) {
            return $space->privacy === 'open' && $space->type === 'interest'
                || $this->membershipStatus($user, $space) === 'active'
                || $this->canModerate($user, $space);
        }

        return match ($space->type) {
            'national' => true,
            'state' => $profile['allStates'] || in_array($space->state_id, [...$profile['homeStates'], ...$profile['viewStates']], true),
            'chapter' => $profile['allStates']
                || in_array($space->chapter_id, $profile['homeChapters'], true)
                || in_array($space->state_id, $profile['viewStates'], true),
            default => false,
        };
    }

    /**
     * Posting needs a home in the space: its chapter/state, or an active
     * group membership. Executives above can read lower spaces, and post
     * there as officials (see canPin).
     */
    public function canPost(User $user, Space $space): bool
    {
        if (! $this->canView($user, $space)) {
            return false;
        }

        return $space->isGroup()
            ? $this->membershipStatus($user, $space) === 'active'
            : $this->forViewer($user)->contains('id', $space->id) || $this->canPin($user, $space);
    }

    public function isGroupAdmin(User $user, Space $space): bool
    {
        return $space->isGroup() && $space->members()
            ->where('users.id', $user->id)
            ->wherePivot('role', 'admin')
            ->wherePivot('status', 'active')
            ->exists();
    }

    /**
     * Moderators (and the secretariat) for the space's level and above;
     * group admins in their own group.
     */
    public function canModerate(User $user, Space $space): bool
    {
        $mod = $this->profile($user)['moderates'];

        if ($mod['all']) {
            return true;
        }

        if ($space->isGroup()) {
            return $this->isGroupAdmin($user, $space);
        }

        return match ($space->type) {
            'state' => in_array($space->state_id, $mod['states'], true),
            'chapter' => in_array($space->chapter_id, $mod['chapters'], true) || in_array($space->state_id, $mod['states'], true),
            default => false,
        };
    }

    /**
     * Pin official posts: moderators, plus the executives of that level.
     */
    /**
     * COM-4: executives of a national, state or chapter space run formal votes there.
     */
    public function canRunFormalVote(User $user, Space $space): bool
    {
        $official = $this->profile($user)['officialOf'];

        return match ($space->type) {
            'national' => $official['national'],
            'state' => in_array($space->state_id, $official['states'], true),
            'chapter' => in_array($space->chapter_id, $official['chapters'], true),
            default => false,
        };
    }

    public function canPin(User $user, Space $space): bool
    {
        if ($this->canModerate($user, $space)) {
            return true;
        }

        $official = $this->profile($user)['officialOf'];

        return match ($space->type) {
            'national' => $official['national'],
            'state' => in_array($space->state_id, $official['states'], true),
            'chapter' => in_array($space->chapter_id, $official['chapters'], true),
            default => false,
        };
    }

    public function membershipStatus(User $user, Space $space): ?string
    {
        $status = $space->members()->where('users.id', $user->id)->first()?->getRelation('pivot')->getAttribute('status');

        return is_string($status) ? $status : null;
    }

    public function syncMembership(User $user, Space $space, string $role = 'member', string $status = 'active'): void
    {
        $existing = $space->members()->where('users.id', $user->id)->first();

        if ($existing === null) {
            $space->members()->attach($user->id, ['role' => $role, 'status' => $status]);
        } elseif ($existing->getRelation('pivot')->getAttribute('status') !== 'active' && $status === 'active') {
            $space->members()->updateExistingPivot($user->id, ['status' => 'active']);
        }
    }

    /**
     * People who share a community with the viewer (for starting chats and
     * inviting to groups): anyone with community access, searched by name
     * or phone. Parents are never listed.
     *
     * @return Collection<int, User>
     */
    public function searchMembers(string $term, User $viewer, int $limit = 12): Collection
    {
        $term = trim($term);

        if (mb_strlen($term) < 2) {
            return collect();
        }

        $digits = preg_replace('/\D+/', '', $term) ?? '';
        $communityRoles = collect(Role::cases())->filter(fn (Role $r) => $r->hasCommunityAccess())->map->value->all();

        return User::query()
            ->whereKeyNot($viewer->getKey())
            ->whereHas('roleAssignments.role', fn ($q) => $q->whereIn('name', $communityRoles))
            ->where(fn ($q) => $q->where('name', 'like', '%'.$term.'%')
                ->when(strlen($digits) >= 4, fn ($q) => $q->orWhere('phone', 'like', '%'.substr($digits, -10).'%')))
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    /**
     * A short label for an author: their best community role, e.g.
     * "Chapter executive · Kano Chapter". Memoised per request.
     */
    public function badgeFor(User $user): ?string
    {
        return $this->profile($user)['badge'];
    }

    private function withDefaults(Space $space): Space
    {
        if ($space->wasRecentlyCreated) {
            $this->ensureDefaultForums($space);
        }

        return $space;
    }

    /**
     * @return array{access: bool, homeStates: list<int>, homeChapters: list<int>, viewStates: list<int>, allStates: bool, moderates: array{all: bool, states: list<int>, chapters: list<int>}, officialOf: array{national: bool, states: list<int>, chapters: list<int>}, badge: ?string}
     */
    private function profile(User $user): array
    {
        if (isset($this->profiles[$user->id])) {
            return $this->profiles[$user->id];
        }

        $profile = [
            'access' => false,
            'homeStates' => [], 'homeChapters' => [], 'viewStates' => [], 'allStates' => false,
            'moderates' => ['all' => false, 'states' => [], 'chapters' => []],
            'officialOf' => ['national' => false, 'states' => [], 'chapters' => []],
            'badge' => null,
        ];

        $assignments = RoleAssignment::query()->with('role')->where('user_id', $user->id)->get()
            ->sortBy(fn (RoleAssignment $a) => $a->roleKey()?->priority() ?? 999);

        foreach ($assignments as $assignment) {
            $role = $assignment->roleKey();

            if (! $role || ! $role->hasCommunityAccess()) {
                continue;
            }

            $place = $assignment->scopeModel();

            if ($place instanceof School && ! $place->membership_status->isMember()) {
                continue; // pending applicants are not members yet
            }

            $profile['access'] = true;
            $profile['badge'] ??= $assignment->label();

            if ($assignment->scope_type === ScopeLevel::National) {
                $profile['allStates'] = true;
            } elseif ($assignment->scope_type === ScopeLevel::State) {
                $profile['homeStates'][] = (int) $assignment->scope_id;
                $profile['viewStates'][] = (int) $assignment->scope_id;
            } elseif ($place instanceof Chapter || $place instanceof School) {
                $profile['homeChapters'][] = $place instanceof Chapter ? $place->id : $place->chapter_id;
                $profile['homeStates'][] = $place->state_id;
            }

            if ($role === Role::Secretariat || ($role === Role::Moderator && $assignment->scope_type === ScopeLevel::National)) {
                $profile['moderates']['all'] = true;
            } elseif ($role === Role::Moderator && $assignment->scope_type === ScopeLevel::State) {
                $profile['moderates']['states'][] = (int) $assignment->scope_id;
            } elseif ($role === Role::Moderator && $assignment->scope_type === ScopeLevel::Chapter) {
                $profile['moderates']['chapters'][] = (int) $assignment->scope_id;
            }

            match ($role) {
                Role::NationalExecutive, Role::Secretariat => $profile['officialOf']['national'] = true,
                Role::StateExecutive => $profile['officialOf']['states'][] = (int) $assignment->scope_id,
                Role::ChapterExecutive => $profile['officialOf']['chapters'][] = (int) $assignment->scope_id,
                default => null,
            };
        }

        foreach (['homeStates', 'homeChapters', 'viewStates'] as $key) {
            $profile[$key] = array_values(array_unique($profile[$key]));
        }

        return $this->profiles[$user->id] = $profile;
    }
}
