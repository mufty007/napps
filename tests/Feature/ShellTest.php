<?php

use App\Enums\Role;
use App\Enums\ScopeLevel;
use App\Models\Chapter;
use App\Models\School;
use App\Models\State;
use App\Models\User;
use App\Support\Authorization\PermissionMatrix;
use Database\Seeders\DatabaseSeeder;
use Spatie\Permission\Models\Role as RoleModel;

it('serves the public pages', function (string $uri, string $text) {
    $this->get($uri)->assertOk()->assertSee($text);
})->with([
    ['/', 'Collect your fees'],
    ['/login', 'Sign in'],
    ['/register', 'Create your school account'],
    ['/join', 'Join your state hub'],
    ['/news', 'News & updates'],
    ['/events', 'Events & webinars'],
    ['/jobs', 'Job board'],
    ['/forgot-password', 'Reset your password'],
]);

it('sends guests to sign in', function () {
    $this->get('/dashboard')->assertRedirect('/login');
    $this->get('/community')->assertRedirect('/login');
});

it('renders a home dashboard for every role', function (Role $role, string $expect) {
    $school = School::factory()->create(['name' => 'Pinnacle Academy']);

    $scopeId = match ($role) {
        Role::StateExecutive => $school->state_id,
        Role::ChapterExecutive, Role::Moderator => $school->chapter_id,
        Role::Proprietor, Role::Bursar, Role::Staff => $school->id,
        default => null,
    };

    $level = $role === Role::Moderator ? ScopeLevel::Chapter : null;

    $this->actingAsRole($role, $scopeId, level: $level);

    $this->get('/dashboard')->assertOk()->assertSee($expect);
})->with([
    [Role::Secretariat, 'Member schools'],
    [Role::NationalExecutive, 'Member schools'],
    [Role::StateExecutive, 'Pinnacle Academy'],
    [Role::ChapterExecutive, 'Pinnacle Academy'],
    [Role::Moderator, 'Pinnacle Academy'],
    [Role::Proprietor, 'Pinnacle Academy'],
    [Role::Bursar, 'Pinnacle Academy'],
    [Role::Staff, 'Pinnacle Academy'],
    [Role::Parent, 'No children linked yet'],
    [Role::LegalPanel, 'No cases assigned'],
]);

it('gives members the community tab and keeps parents out', function () {
    $school = School::factory()->create();

    $this->actingAsRole(Role::Proprietor, $school->id);
    $this->get('/dashboard')->assertSee(route('community.index'));
    $this->get('/community')->assertOk()->assertSee('Start a post')->assertSee('Forums');

    $this->actingAsRole(Role::Parent);
    $this->get('/dashboard')->assertDontSee(route('community.index'));
    $this->get('/community')->assertForbidden();
});

it('registers every domain module', function () {
    foreach (config('modules.enabled') as $module) {
        expect(app()->getProviders("Modules\\{$module}\\Providers\\{$module}ServiceProvider"))->not->toBeEmpty();
    }
});

it('seeds the org tree, roles and the secretariat account', function () {
    $this->seed(DatabaseSeeder::class);

    expect(State::query()->count())->toBe(37)
        ->and(Chapter::query()->count())->toBe(37)
        ->and(RoleModel::query()->pluck('name')->sort()->values()->all())
        ->toBe(collect(Role::cases())->map->value->sort()->values()->all());

    $admin = User::query()->where('phone', '+2348000000000')->sole();
    expect($admin->holdsRole(Role::Secretariat))->toBeTrue();
});

it('only references catalogued permissions in the role matrix', function () {
    $catalog = array_keys(PermissionMatrix::permissions());

    foreach (PermissionMatrix::matrix() as $role => $permissions) {
        expect(array_diff($permissions, $catalog))->toBe([], "Role {$role} references unknown permissions");
    }
});

it('logs changes to org records', function () {
    $this->seedRoles();
    $school = School::factory()->create();

    $school->update(['name' => 'Renamed Academy']);

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'org',
        'subject_type' => School::class,
        'subject_id' => $school->id,
        'event' => 'updated',
    ]);
});
