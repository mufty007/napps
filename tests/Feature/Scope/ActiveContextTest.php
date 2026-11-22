<?php

use App\Enums\Role;
use App\Enums\ScopeLevel;
use App\Models\Chapter;
use App\Models\Concerns\BelongsToSchool;
use App\Models\RoleAssignment;
use App\Models\School;
use App\Models\State;
use App\Models\User;
use App\Support\Scope\ActiveContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two states, each with two chapters, each with two schools.
 */
beforeEach(function () {
    $this->seedRoles();

    $this->states = State::factory()->count(2)->create();
    $this->chapters = $this->states->flatMap(fn ($state) => Chapter::factory()->count(2)->for($state)->create());
    $this->schools = $this->chapters->flatMap(fn ($chapter) => School::factory()->count(2)->for($chapter)->create());
});

function visibleSchoolIds(): array
{
    return School::query()->orderBy('id')->pluck('id')->all();
}

it('shows everything to national roles', function () {
    $this->actingAsRole(Role::NationalExecutive);

    expect(visibleSchoolIds())->toHaveCount(8);
});

it('limits state executives to their state', function () {
    $state = $this->states[0];
    $this->actingAsRole(Role::StateExecutive, $state->id);

    expect(visibleSchoolIds())->toBe($this->schools->where('state_id', $state->id)->pluck('id')->sort()->values()->all());
});

it('limits chapter executives to their chapter', function () {
    $chapter = $this->chapters[1];
    $this->actingAsRole(Role::ChapterExecutive, $chapter->id);

    expect(visibleSchoolIds())->toBe($this->schools->where('chapter_id', $chapter->id)->pluck('id')->sort()->values()->all());
});

it('limits school roles to their school', function () {
    $school = $this->schools[3];
    $this->actingAsRole(Role::Bursar, $school->id);

    expect(visibleSchoolIds())->toBe([$school->id]);
});

it('shows no org data to personal roles or users without a role', function () {
    $this->actingAsRole(Role::Parent);
    expect(visibleSchoolIds())->toBe([]);

    $this->actingAs(User::factory()->create());
    expect(visibleSchoolIds())->toBe([]);
});

it('does not restrict system context (console, queue, guests)', function () {
    expect(visibleSchoolIds())->toHaveCount(8);
});

it('keeps state_id in step with the chapter', function () {
    $school = $this->schools[0];
    $other = $this->chapters->firstWhere('state_id', '!=', $school->state_id);

    $school->update(['chapter_id' => $other->id]);

    expect($school->fresh()->state_id)->toBe($other->state_id);
});

it('switches between roles and rescopes queries', function () {
    $user = User::factory()->withTwoFactor()->create();
    $schoolA = $this->schools[0];
    $chapter = $this->chapters[3];

    $proprietor = RoleAssignment::grant($user, Role::Proprietor, ScopeLevel::School, $schoolA->id);
    $executive = RoleAssignment::grant($user, Role::ChapterExecutive, ScopeLevel::Chapter, $chapter->id);

    // Highest-priority role is the default.
    $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee($chapter->name);
    expect(app(ActiveContext::class)->role())->toBe(Role::ChapterExecutive);

    $this->post('/context', ['assignment' => $proprietor->id])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas(ActiveContext::SESSION_KEY, $proprietor->id);

    $this->get('/dashboard')->assertOk()->assertSee($schoolA->name);
    expect(app(ActiveContext::class)->role())->toBe(Role::Proprietor)
        ->and(visibleSchoolIds())->toBe([$schoolA->id]);
});

it('refuses to switch to someone else\'s role', function () {
    $user = $this->actingAsRole(Role::Proprietor, $this->schools[0]->id);
    $stranger = User::factory()->create();
    $theirs = RoleAssignment::grant($stranger, Role::NationalExecutive, ScopeLevel::National);

    $this->post('/context', ['assignment' => $theirs->id])->assertNotFound();

    expect(app(ActiveContext::class)->role())->toBe(Role::Proprietor);
});

it('grants permissions from the active role only', function () {
    $user = User::factory()->withTwoFactor()->create();
    RoleAssignment::grant($user, Role::Proprietor, ScopeLevel::School, $this->schools[0]->id);
    $exec = RoleAssignment::grant($user, Role::NationalExecutive, ScopeLevel::National);

    $this->actingAs($user);
    expect($user->can('templates.publish'))->toBeTrue()
        ->and($user->can('fees.structures.manage'))->toBeFalse();

    $this->post('/context', ['assignment' => $user->roleAssignments()->where('id', '!=', $exec->id)->value('id')]);
    expect($user->can('fees.structures.manage'))->toBeTrue()
        ->and($user->can('templates.publish'))->toBeFalse();
});

it('refuses role grants at the wrong level', function () {
    RoleAssignment::grant(User::factory()->create(), Role::Bursar, ScopeLevel::National);
})->throws(InvalidArgumentException::class);

it('scopes school-owned module records through BelongsToSchool', function () {
    Schema::create('scoped_things', function (Blueprint $table) {
        $table->id();
        $table->foreignId('school_id');
    });

    $thing = new class extends Model
    {
        use BelongsToSchool;

        protected $table = 'scoped_things';

        public $timestamps = false;

        protected $guarded = [];
    };

    $this->schools->each(fn ($school) => $thing->newQuery()->create(['school_id' => $school->id]));

    $state = $this->states[1];
    $chapter = $this->chapters[0];
    $school = $this->schools[5];

    $this->actingAsRole(Role::StateExecutive, $state->id);
    expect($thing->newQuery()->pluck('school_id')->sort()->values()->all())
        ->toBe($this->schools->where('state_id', $state->id)->pluck('id')->sort()->values()->all());

    $this->actingAsRole(Role::ChapterExecutive, $chapter->id);
    expect($thing->newQuery()->pluck('school_id')->sort()->values()->all())
        ->toBe($this->schools->where('chapter_id', $chapter->id)->pluck('id')->sort()->values()->all());

    $this->actingAsRole(Role::Staff, $school->id);
    expect($thing->newQuery()->pluck('school_id')->all())->toBe([$school->id]);

    $this->actingAsRole(Role::Parent);
    expect($thing->newQuery()->count())->toBe(0);
});
