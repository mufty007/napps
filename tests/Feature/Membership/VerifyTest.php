<?php

use App\Enums\MembershipStatus;
use App\Enums\Role;

beforeEach(function () {
    $this->seedRoles();
    $this->org = membershipOrg();
    $chapter = $this->org['katsinaNorth'];

    $this->active = memberSchool($chapter, ['name' => 'Daura Model International School', 'membership_number' => 'NAPPS-KT-00001']);
    $this->owing = memberSchool($chapter, ['name' => 'Mai\'adua Pinnacle Academy', 'membership_number' => 'NAPPS-KT-00002', 'membership_status' => MembershipStatus::Owing]);
    $this->suspended = memberSchool($chapter, ['name' => 'Daura Unity College', 'membership_number' => 'NAPPS-KT-00003', 'membership_status' => MembershipStatus::Suspended]);
    $this->pending = memberSchool($chapter, ['name' => 'Daura Bright Stars', 'membership_status' => MembershipStatus::Pending]);
});

it('lets anyone search member schools by name or number', function () {
    $this->get(route('verify.index', ['q' => 'daura']))
        ->assertOk()
        ->assertSee('Daura Model International School')
        ->assertSee('Daura Unity College')
        ->assertDontSee('Daura Bright Stars');

    $this->get(route('verify.index', ['q' => 'napps-kt-00002']))
        ->assertSee('Pinnacle Academy')
        ->assertDontSee('Daura Model');
});

it('shows a verified member without revealing dues', function () {
    $this->get(route('verify.show', 'NAPPS-KT-00002'))
        ->assertOk()
        ->assertSee('Verified NAPPS member school')
        ->assertSee('Katsina North Chapter')
        ->assertDontSee('Owing');
});

it('flags a suspended membership', function () {
    $this->get(route('verify.show', 'NAPPS-KT-00003'))->assertOk()->assertSee('Membership suspended');
});

it('answers 404 for unknown numbers and non-members', function () {
    $this->get(route('verify.show', 'NAPPS-KT-09999'))->assertNotFound()->assertSee('No member school with this number');

    $this->pending->update(['membership_number' => 'NAPPS-KT-00004']);
    $this->get(route('verify.show', 'NAPPS-KT-00004'))->assertNotFound();

    $this->active->delete();
    $this->get(route('verify.show', 'NAPPS-KT-00001'))->assertNotFound();
});

it('works the same for a signed-in member outside the area', function () {
    $this->actingAsRole(Role::ChapterExecutive, $this->org['kanoChapter']->id);

    $this->get(route('verify.show', 'NAPPS-KT-00001'))->assertOk()->assertSee('Daura Model International School');
});
