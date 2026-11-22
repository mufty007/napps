<?php

use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Models\School;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Modules\Membership\Enums\ApplicationStatus;
use Modules\Membership\Enums\DueStatus;
use Modules\Membership\Models\MembershipApplication;
use Modules\Membership\Models\MembershipDue;

beforeEach(function () {
    Storage::fake('local');
    $this->seedRoles();
    $this->org = membershipOrg();
    $this->chapterExec = officer(Role::ChapterExecutive, $this->org['kanoChapter']->id);
    $this->stateExec = officer(Role::StateExecutive, $this->org['kano']->id);
    $this->applicant = User::factory()->create(['phone' => '+2348031112222']);
});

function applicationPayload(array $overrides = []): array
{
    return [
        'name' => 'Al-Ihsan Model Academy',
        'registration_number' => 'rc 1234567',
        'ministry_approval_number' => 'MOE/KN/2211',
        'levels' => ['nursery', 'primary'],
        'pupil_count' => 640,
        'state_id' => test()->org['kano']->id,
        'chapter_id' => test()->org['kanoChapter']->id,
        'lga' => 'Nassarawa',
        'address' => '12 Zaria Road, Gyadi-Gyadi',
        'latitude' => '11.9719',
        'longitude' => '8.5311',
        'phone' => '0803 111 2222',
        'cac_certificate' => fakePdf('cac.pdf'),
        'premises_photo' => UploadedFile::fake()->image('front.png'),
        'declaration' => '1',
        ...$overrides,
    ];
}

function submitApplication(array $overrides = []): MembershipApplication
{
    test()->actingAs(test()->applicant)
        ->post(route('membership.apply.store'), applicationPayload($overrides))
        ->assertSessionHasNoErrors();

    return MembershipApplication::withoutGlobalScopes()->latest('id')->firstOrFail();
}

it('lets anyone signed in apply, and notifies the chapter (MEM-1)', function () {
    $this->actingAs($this->applicant)->get(route('membership.apply'))->assertOk()->assertSee('Kano Chapter');

    $application = submitApplication();
    $school = $application->school;

    expect($application->status)->toBe(ApplicationStatus::Submitted)
        ->and($school->membership_status)->toBe(MembershipStatus::Pending)
        ->and($school->registration_number)->toBe('RC 1234567')
        ->and($school->state_id)->toBe($this->org['kano']->id)
        ->and($school->phone)->toBe('+2348031112222')
        ->and($application->getFirstMedia('cac_certificate'))->not->toBeNull()
        ->and($application->getFirstMedia('cac_certificate')->disk)->toBe('local')
        ->and($application->getFirstMedia('premises_photo'))->not->toBeNull();

    expect(smsTo($this->chapterExec))->toHaveCount(1)
        ->and(smsTo($this->chapterExec)[0])->toContain('Al-Ihsan Model Academy has applied to join Kano Chapter')
        ->and(smsTo($this->applicant)[0])->toContain($application->reference());

    // Status visible to the applicant on home and on the application page.
    $this->get('/dashboard')->assertSee('Awaiting chapter verification');
    $this->get(route('membership.apply.show', $application))->assertOk()->assertSee('Kano Chapter is reviewing it');
    $this->get(route('membership.apply.document', [$application, 'cac_certificate']))->assertOk();
});

it('requires the PRD fields and documents', function () {
    $this->actingAs($this->applicant)
        ->post(route('membership.apply.store'), applicationPayload([
            'cac_certificate' => null,
            'levels' => [],
            'chapter_id' => $this->org['katsinaNorth']->id, // not in Kano
            'latitude' => '51.5',
            'declaration' => null,
        ]))
        ->assertSessionHasErrors(['cac_certificate', 'levels', 'chapter_id', 'latitude', 'declaration']);

    expect(School::withoutGlobalScopes()->count())->toBe(0);
});

it('refuses a CAC number that is already registered', function () {
    memberSchool($this->org['kanoChapter'], ['registration_number' => 'RC 1234567']);

    $this->actingAs($this->applicant)
        ->post(route('membership.apply.store'), applicationPayload(['registration_number' => 'RC 1234567']))
        ->assertSessionHasErrors('registration_number');
});

it('limits how many applications one person can have open', function () {
    config(['napps.membership.max_open_applications' => 1]);
    submitApplication();

    $this->post(route('membership.apply.store'), applicationPayload(['registration_number' => 'RC 999']))
        ->assertSessionHasErrors('name');
});

it('keeps applications to their chapter and state', function () {
    $application = submitApplication();
    $otherChapterExec = officer(Role::ChapterExecutive, $this->org['katsinaNorth']->id);

    $this->actingAs($this->chapterExec)->get(route('membership.applications.index'))->assertOk()->assertSee('Al-Ihsan Model Academy');
    $this->actingAs($this->chapterExec)->get(route('membership.applications.show', $application))->assertOk();

    $this->actingAs($otherChapterExec)->get(route('membership.applications.index'))->assertDontSee('Al-Ihsan Model Academy');
    $this->actingAs($otherChapterExec)->get(route('membership.applications.show', $application))->assertNotFound();
    $this->actingAs($otherChapterExec)->post(route('membership.applications.verify', $application))->assertNotFound();

    // Another applicant cannot open it either.
    $this->actingAs(User::factory()->create())->get(route('membership.apply.show', $application))->assertNotFound();
});

it('runs chapter verification then state approval, and issues the certificate (MEM-2)', function () {
    issuedCharge(['due_date' => now()->subWeek()->toDateString()]);
    $application = submitApplication();

    // The chapter cannot approve; the state cannot verify.
    $this->actingAs($this->chapterExec)->post(route('membership.applications.approve', $application))->assertForbidden();
    $this->actingAs($this->stateExec)->post(route('membership.applications.verify', $application))->assertForbidden();

    $this->actingAs($this->chapterExec)
        ->post(route('membership.applications.verify', $application), ['note' => 'Visited, all in order.'])
        ->assertRedirect(route('membership.applications.index'));

    expect($application->fresh()->status)->toBe(ApplicationStatus::Verified)
        ->and(smsTo($this->stateExec)[0])->toContain('needs state approval');

    $this->actingAs($this->stateExec)
        ->post(route('membership.applications.approve', $application))
        ->assertSessionHas('status');

    $application->refresh();
    $school = School::withoutGlobalScopes()->find($application->school_id);

    expect($application->status)->toBe(ApplicationStatus::Approved)
        ->and($application->approved_by)->toBe($this->stateExec->id)
        ->and($school->membership_number)->toBe('NAPPS-KN-00001')
        ->and($this->applicant->holdsRole(Role::Proprietor))->toBeTrue()
        // This year's dues are billed straight away; already overdue, so Owing until paid (MEM-3).
        ->and(MembershipDue::withoutGlobalScopes()->where('school_id', $school->id)->sole()->status)->toBe(DueStatus::Unpaid)
        ->and($school->membership_status)->toBe(MembershipStatus::Owing);

    $approvalSms = collect(smsTo($this->applicant))->last();
    expect($approvalSms)->toContain('NAPPS-KN-00001')->toContain('/membership/certificates/');

    // The certificate link in the SMS works without signing in; tampering breaks it.
    preg_match('/(https?:\/\/\S+)/', $approvalSms, $m);
    auth()->logout();
    $this->get($m[1])->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->get(route('membership.certificate', $school->id))->assertForbidden();
});

it('lets the chapter approve directly when state approval is off', function () {
    config(['napps.membership.state_approval_required' => false]);
    $application = submitApplication();

    $this->actingAs($this->chapterExec)->post(route('membership.applications.verify', $application));

    expect($application->fresh()->status)->toBe(ApplicationStatus::Approved)
        ->and($application->fresh()->school->membership_number)->toBe('NAPPS-KN-00001')
        ->and($application->fresh()->school->membership_status)->toBe(MembershipStatus::Active);
});

it('numbers members per state in sequence', function () {
    memberSchool($this->org['kanoChapter'], ['membership_number' => 'NAPPS-KN-00041']);
    config(['napps.membership.state_approval_required' => false]);
    $application = submitApplication();

    $this->actingAs($this->chapterExec)->post(route('membership.applications.verify', $application));

    expect($application->fresh()->school->membership_number)->toBe('NAPPS-KN-00042');
});

it('rejects with a reason the applicant receives, and drops the school', function () {
    $application = submitApplication();

    $this->actingAs($this->chapterExec)
        ->post(route('membership.applications.reject', $application), ['reason' => ''])
        ->assertSessionHasErrors('reason');

    $this->actingAs($this->chapterExec)
        ->post(route('membership.applications.reject', $application), ['reason' => 'The CAC certificate is for another business.'])
        ->assertRedirect();

    $application->refresh();
    expect($application->status)->toBe(ApplicationStatus::Rejected)
        ->and($application->rejected_stage)->toBe('chapter')
        ->and(School::withoutGlobalScopes()->find($application->school_id)->trashed())->toBeTrue()
        ->and(collect(smsTo($this->applicant))->last())->toContain('The CAC certificate is for another business.');

    // Cannot be decided twice.
    $this->actingAs($this->stateExec)->post(route('membership.applications.approve', $application))->assertForbidden();

    // The applicant sees why, and may apply again with the same CAC number.
    $this->actingAs($this->applicant)->get(route('membership.apply.show', $application))->assertSee('The CAC certificate is for another business.');
    submitApplication();
});

it('logs who opened an application document', function () {
    $application = submitApplication();

    $this->actingAs($this->chapterExec)
        ->get(route('membership.applications.document', [$application, 'cac_certificate']))
        ->assertOk();

    $this->assertDatabaseHas('activity_log', [
        'event' => 'document-viewed',
        'subject_id' => $application->id,
        'causer_id' => $this->chapterExec->id,
    ]);
});

it('uses signed links for certificates', function () {
    $school = memberSchool($this->org['kanoChapter'], ['membership_number' => 'NAPPS-KN-00007']);

    $this->get(URL::temporarySignedRoute('membership.certificate', now()->addHour(), ['school' => $school->id]))->assertOk();
    $this->get(URL::temporarySignedRoute('membership.certificate', now()->subMinute(), ['school' => $school->id]))->assertForbidden();
});
