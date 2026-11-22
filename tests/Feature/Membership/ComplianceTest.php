<?php

use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Models\User;
use Modules\Membership\Enums\PaymentMethod;
use Modules\Membership\Models\MembershipDue;
use Modules\Membership\Models\MembershipSuspension;
use Modules\Membership\Services\DuesLedger;

beforeEach(function () {
    $this->seedRoles();
    $this->org = membershipOrg();
    $this->proprietor = User::factory()->create();
    $this->school = memberSchool($this->org['kanoChapter'], ['name' => 'Tarauni Heritage Academy'], $this->proprietor);
});

function payAll($school): void
{
    $dues = MembershipDue::withoutGlobalScopes()->where('school_id', $school->id)->unpaid()->get();
    app(DuesLedger::class)->recordPayment($school, $dues, PaymentMethod::Cash, (int) $dues->sum('amount_kobo'), now());
}

it('turns schools Owing when dues fall due, and Active again once paid', function () {
    issuedCharge(['due_date' => now()->addDays(3)->toDateString()]);
    $this->artisan('membership:refresh-compliance')->assertSuccessful();
    expect($this->school->fresh()->membership_status)->toBe(MembershipStatus::Active);

    $this->travel(4)->days();
    $this->artisan('membership:refresh-compliance')->assertSuccessful();
    expect($this->school->fresh()->membership_status)->toBe(MembershipStatus::Owing);

    payAll($this->school);
    expect($this->school->fresh()->membership_status)->toBe(MembershipStatus::Active);

    $this->assertDatabaseHas('activity_log', ['log_name' => 'org', 'subject_id' => $this->school->id, 'event' => 'updated']);
});

it('never touches pending applicants', function () {
    $pending = memberSchool($this->org['kanoChapter'], ['membership_status' => MembershipStatus::Pending]);
    issuedCharge();

    $this->artisan('membership:refresh-compliance');

    expect($pending->fresh()->membership_status)->toBe(MembershipStatus::Pending);
});

it('lets state and national executives suspend and reinstate, with a reason', function () {
    issuedCharge();
    $stateExec = officer(Role::StateExecutive, $this->org['kano']->id);

    $this->actingAs(officer(Role::ChapterExecutive, $this->org['kanoChapter']->id))
        ->post(route('membership.schools.suspend', $this->school), ['reason' => 'No dues for two years'])
        ->assertForbidden();

    $this->actingAs($stateExec)
        ->post(route('membership.schools.suspend', $this->school), ['reason' => 'No dues for two years'])
        ->assertSessionHas('status');

    expect($this->school->fresh()->membership_status)->toBe(MembershipStatus::Suspended)
        ->and(collect(smsTo($this->proprietor))->last())->toContain('suspended (No dues for two years)');

    // Paying does not lift a suspension an executive applied.
    payAll($this->school);
    expect($this->school->fresh()->membership_status)->toBe(MembershipStatus::Suspended);

    $this->post(route('membership.schools.reinstate', $this->school), ['note' => 'Settled at the AGM'])->assertSessionHas('status');

    expect($this->school->fresh()->membership_status)->toBe(MembershipStatus::Active)
        ->and(MembershipSuspension::withoutGlobalScopes()->sole()->lifted_by)->toBe($stateExec->id)
        ->and(collect(smsTo($this->proprietor))->last())->toContain('reinstated');
});

it('reinstates to Owing when dues are still overdue', function () {
    issuedCharge();
    $exec = officer(Role::NationalExecutive);

    $this->actingAs($exec)->post(route('membership.schools.suspend', $this->school), ['reason' => 'Misconduct report']);
    $this->post(route('membership.schools.reinstate', $this->school));

    expect($this->school->fresh()->membership_status)->toBe(MembershipStatus::Owing);
});

it('suspends automatically after the configured overdue period and lifts it on payment', function () {
    config(['napps.membership.suspend_after_days_overdue' => 90]);
    issuedCharge(['due_date' => now()->subDays(30)->toDateString()]);

    $this->artisan('membership:refresh-compliance');
    expect($this->school->fresh()->membership_status)->toBe(MembershipStatus::Owing);

    $this->travel(61)->days();
    $this->artisan('membership:refresh-compliance');
    expect($this->school->fresh()->membership_status)->toBe(MembershipStatus::Suspended)
        ->and(MembershipSuspension::withoutGlobalScopes()->sole()->automatic)->toBeTrue();

    payAll($this->school);
    expect($this->school->fresh()->membership_status)->toBe(MembershipStatus::Active)
        ->and(MembershipSuspension::withoutGlobalScopes()->sole()->lifted_at)->not->toBeNull();
});

it('does not suspend automatically unless configured', function () {
    issuedCharge(['due_date' => now()->subYear()->toDateString()]);

    $this->artisan('membership:refresh-compliance');

    expect($this->school->fresh()->membership_status)->toBe(MembershipStatus::Owing)
        ->and(MembershipSuspension::withoutGlobalScopes()->count())->toBe(0);
});
