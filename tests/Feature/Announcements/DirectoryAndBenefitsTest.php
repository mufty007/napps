<?php

use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Enums\ScopeLevel;
use App\Models\RoleAssignment;
use App\Models\School;
use App\Models\User;
use Modules\Announcements\Models\Election;
use Modules\Announcements\Models\Event;
use Modules\Announcements\Seeders\AnnouncementsDemoSeeder;

beforeEach(function () {
    $this->seedRoles();
    $this->org = membershipOrg();
});

it('shows a proprietor the member schools of their own chapter only', function () {
    $mine = memberSchool($this->org['kanoChapter'], ['name' => 'Al-Ihsan Model Academy']);
    memberSchool($this->org['kanoChapter'], ['name' => 'Tarauni Heritage Academy']);
    memberSchool($this->org['katsinaNorth'], ['name' => 'Daura Model International School']);
    School::factory()->for($this->org['kanoChapter'])->status(MembershipStatus::Pending)->create(['name' => 'Pending Applicant School']);
    School::factory()->for($this->org['kanoChapter'])->status(MembershipStatus::Suspended)->create(['name' => 'Suspended Academy']);

    $proprietor = User::factory()->withTwoFactor()->create(['name' => 'Alhaji Bashir Lawan']);
    RoleAssignment::grant($proprietor, Role::Proprietor, ScopeLevel::School, $mine->id);
    $this->actingAs($proprietor);

    $this->get(route('directory.index'))
        ->assertOk()
        ->assertSee('Al-Ihsan Model Academy')
        ->assertSee('Tarauni Heritage Academy')
        ->assertSee('Alhaji Bashir Lawan')
        ->assertDontSee('Daura Model International School')
        ->assertDontSee('Pending Applicant School')
        ->assertDontSee('Suspended Academy');

    // They cannot widen the view to another chapter.
    $this->get(route('directory.index', ['chapter_id' => $this->org['katsinaNorth']->id]))
        ->assertOk()->assertDontSee('Daura Model International School');
});

it('searches the directory by name or LGA', function () {
    memberSchool($this->org['kanoChapter'], ['name' => 'Gwale Excellence School', 'lga' => 'Gwale']);
    memberSchool($this->org['kanoChapter'], ['name' => 'Fagge Unity College', 'lga' => 'Fagge']);

    $this->actingAsRole(Role::ChapterExecutive, $this->org['kanoChapter']->id);

    $this->get(route('directory.index', ['q' => 'Gwale']))->assertOk()->assertSee('Gwale Excellence School')->assertDontSee('Fagge Unity College');
    $this->get(route('directory.index', ['q' => 'zzz']))->assertOk()->assertSee('No schools found');
});

it('lets a state executive browse the state and national roles every chapter', function () {
    memberSchool($this->org['kanoChapter'], ['name' => 'Kano Test School']);
    memberSchool($this->org['katsinaNorth'], ['name' => 'Katsina Test School']);

    $this->actingAsRole(Role::StateExecutive, $this->org['kano']->id);
    $this->get(route('directory.index'))->assertSee('Kano Test School')->assertDontSee('Katsina Test School');

    $this->actingAsRole(Role::NationalExecutive);
    $this->get(route('directory.index'))->assertSee('Kano Test School')->assertSee('Katsina Test School');
    $this->get(route('directory.index', ['chapter_id' => $this->org['katsinaNorth']->id]))
        ->assertSee('Katsina Test School')->assertDontSee('Kano Test School');
});

it('keeps the directory from parents, bursars and staff', function (Role $role) {
    $school = memberSchool($this->org['kanoChapter']);

    $this->actingAsRole($role, $role === Role::Parent ? null : $school->id);
    $this->get(route('directory.index'))->assertForbidden();
})->with([Role::Parent, Role::Bursar, Role::Staff]);

it('sends guests to sign in for the directory', function () {
    $this->get(route('directory.index'))->assertRedirect('/login');
});

it('serves the benefits page to guests and signed-in members', function () {
    $this->get(route('benefits'))->assertOk()->assertSee('What membership gives your school')->assertSee('One school, one vote')->assertSee('Join as a school');

    $this->actingAsRole(Role::Proprietor, memberSchool($this->org['kanoChapter'])->id);
    $this->get(route('benefits'))->assertOk()->assertSee('What membership gives your school')->assertDontSee('Join as a school');
});

it('lists directory and benefits in the navigation for the right roles', function () {
    $this->actingAsRole(Role::Proprietor, memberSchool($this->org['kanoChapter'])->id);
    $this->get('/dashboard')->assertSee(route('directory.index'))->assertSee(route('benefits'));

    $this->actingAsRole(Role::Bursar, memberSchool($this->org['kanoChapter'])->id);
    $this->get('/dashboard')->assertDontSee(route('directory.index'))->assertSee(route('benefits'));

    $this->actingAsRole(Role::Parent);
    $this->get('/dashboard')->assertDontSee(route('directory.index'))->assertDontSee(route('benefits'));
});

it('seeds agenda, minutes, RSVPs, check-ins and a chapter election', function () {
    User::factory()->create(['phone' => '+2348000000001']);
    $kano = $this->org['kanoChapter'];
    $kano->update(['name' => 'Kano Chapter']);

    $this->seed(AnnouncementsDemoSeeder::class);
    $this->seed(AnnouncementsDemoSeeder::class); // idempotent

    $past = Event::query()->where('slug', 'kano-chapter-quarterly-meeting')->firstOrFail();
    expect($past->minutes)->not->toBeNull()
        ->and($past->agendaItems())->not->toBeEmpty()
        ->and(Event::query()->where('slug', 'katsina-north-agm-2026')->firstOrFail()->agendaItems())->not->toBeEmpty();

    $election = Election::query()->where('chapter_id', $kano->id)->sole();
    expect($election->isOpen())->toBeTrue()->and($election->candidates()->count())->toBe(3);
});
