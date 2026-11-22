<?php

use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Enums\ScopeLevel;
use App\Models\RoleAssignment;
use App\Models\User;
use Modules\Membership\Enums\ApplicationStatus;
use Modules\Membership\Enums\PaymentMethod;
use Modules\Membership\Models\MembershipApplication;
use Modules\Membership\Models\MembershipDue;
use Modules\Membership\Services\ComplianceService;
use Modules\Membership\Services\DuesLedger;

beforeEach(function () {
    $this->seedRoles();
    $this->org = membershipOrg();
    $this->proprietor = User::factory()->create();
    $this->paid = memberSchool($this->org['kanoChapter'], ['name' => 'Al-Ihsan Model Academy', 'membership_number' => 'NAPPS-KN-00001'], $this->proprietor);
    $this->owing = memberSchool($this->org['kanoChapter'], ['name' => 'Kano Capital International School']);
    $this->daura = memberSchool($this->org['katsinaNorth'], ['name' => 'Daura Model International School']);

    issuedCharge(['share_national' => 40, 'share_state' => 30, 'share_chapter' => 30]);
    $due = MembershipDue::withoutGlobalScopes()->where('school_id', $this->paid->id)->sole();
    app(DuesLedger::class)->recordPayment($this->paid, collect([$due]), PaymentMethod::Cash, 2_500_000, now());
    app(ComplianceService::class)->refresh();
});

it('gives national the dues collected against expected and compliance by state', function () {
    $this->actingAsRole(Role::NationalExecutive);

    $this->get('/dashboard')
        ->assertOk()
        ->assertSee('Dues '.now()->year)
        ->assertSee('₦25,000')       // collected
        ->assertSee('₦75,000')       // expected
        ->assertSee('Compliance by state')
        ->assertSee('Kano')
        ->assertSee('Katsina')
        ->assertSee("National's share");
});

it('ranks chapters for a state and shows pending approvals', function () {
    $applicant = User::factory()->create();
    $pending = memberSchool($this->org['kanoChapter'], ['membership_status' => MembershipStatus::Pending]);
    MembershipApplication::withoutGlobalScopes()->create([
        'school_id' => $pending->id, 'applicant_id' => $applicant->id,
        'status' => ApplicationStatus::Verified, 'submitted_at' => now(),
    ]);

    $this->actingAsRole(Role::StateExecutive, $this->org['kano']->id);

    $this->get('/dashboard')
        ->assertSee('Chapters by compliance')
        ->assertSee('Kano Chapter')
        ->assertDontSee('Katsina North Chapter')
        ->assertSee('1 school is waiting for your approval')
        ->assertSee("Kano's share")
        ->assertSee('₦7,500'); // state's 30% of the one payment
});

it('lists schools to follow up for a chapter', function () {
    $this->actingAsRole(Role::ChapterExecutive, $this->org['kanoChapter']->id);

    $this->get('/dashboard')
        ->assertSee('Follow up on dues')
        ->assertSee('Kano Capital International School')
        ->assertDontSee('Daura Model International School');
});

it('shows the proprietor their NAPPS status and a pay button, but not the bursar', function () {
    $owingProprietor = User::factory()->withTwoFactor()->create();
    RoleAssignment::grant($owingProprietor, Role::Proprietor, ScopeLevel::School, $this->owing->id);

    $this->actingAs($owingProprietor)->get('/dashboard')
        ->assertSee('NAPPS membership')
        ->assertSee('Pay ₦25,000')
        ->assertSee('Owing');

    $this->actingAs($this->proprietor)->get('/dashboard')->assertSee('Dues paid up')->assertSee('NAPPS-KN-00001');

    $this->actingAsRole(Role::Bursar, $this->owing->id);
    $this->get('/dashboard')->assertSee('NAPPS membership')->assertDontSee('Pay ₦25,000');
    $this->get(route('membership.dues.index'))->assertForbidden();
});

it('puts membership screens in each role\'s navigation', function (Role $role, array $see, array $dontSee) {
    $scope = match ($role) {
        Role::StateExecutive => $this->org['kano']->id,
        Role::ChapterExecutive => $this->org['kanoChapter']->id,
        Role::Proprietor => $this->paid->id,
        default => null,
    };

    $this->actingAsRole($role, $scope);
    $response = $this->get('/dashboard')->assertOk();

    foreach ($see as $route) {
        $response->assertSee(route($route), false);
    }
    foreach ($dontSee as $route) {
        $response->assertDontSee(route($route), false);
    }
})->with([
    [Role::NationalExecutive, ['membership.schools.index', 'membership.charges.index'], ['membership.applications.index']],
    [Role::StateExecutive, ['membership.schools.index', 'membership.applications.index', 'membership.charges.index'], []],
    [Role::ChapterExecutive, ['membership.schools.index', 'membership.applications.index', 'membership.charges.index'], []],
    [Role::Proprietor, ['membership.dues.index'], ['membership.schools.index', 'membership.charges.index']],
]);

it('scopes the registry to the active role', function () {
    $this->actingAsRole(Role::ChapterExecutive, $this->org['katsinaNorth']->id);

    $this->get(route('membership.schools.index'))->assertSee('Daura Model International School')->assertDontSee('Al-Ihsan Model Academy');
    $this->get(route('membership.schools.show', $this->paid))->assertNotFound();
    $this->get(route('membership.schools.show', $this->daura))->assertOk()->assertSee('Annual dues');
});
