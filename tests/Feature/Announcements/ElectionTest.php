<?php

use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Enums\ScopeLevel;
use App\Models\RoleAssignment;
use App\Models\School;
use App\Models\User;
use App\Support\Scope\ActiveContext;
use Illuminate\Database\UniqueConstraintViolationException;
use Modules\Announcements\Models\Election;
use Modules\Announcements\Models\ElectionVote;

beforeEach(function () {
    $this->seedRoles();
    $this->org = membershipOrg();
});

function electionVoter(School $school): User
{
    $user = User::factory()->withTwoFactor()->create();
    RoleAssignment::grant($user, Role::Proprietor, ScopeLevel::School, $school->id);

    return $user;
}

/** An open Kano Chapter election with two candidates. */
function openElection(array $org, array $attributes = []): Election
{
    $election = Election::query()->create([
        'title' => 'Chairman 2026',
        'state_id' => $org['kano']->id,
        'chapter_id' => $org['kanoChapter']->id,
        'status' => Election::OPEN,
        'created_by' => User::factory()->create()->id,
        ...$attributes,
    ]);

    $election->candidates()->createMany([
        ['name' => 'Candidate A', 'sort' => 1],
        ['name' => 'Candidate B', 'sort' => 2],
    ]);

    return $election;
}

it('lets a proprietor cast their school\'s single vote', function () {
    $school = memberSchool($this->org['kanoChapter']);
    $election = openElection($this->org);
    $candidate = $election->candidates()->first();
    $user = electionVoter($school);
    $this->actingAs($user);

    $this->get(route('elections.index'))->assertOk()->assertSee('Chairman 2026')->assertSee('Vote needed');
    $this->get(route('elections.show', $election))->assertOk()->assertSee('Candidate A')->assertSee('Cast vote');

    $this->post(route('elections.vote', $election), ['candidate_id' => $candidate->id])
        ->assertRedirect(route('elections.show', $election))
        ->assertSessionHasNoErrors();

    $vote = ElectionVote::query()->sole();
    expect($vote->school_id)->toBe($school->id)
        ->and($vote->candidate_id)->toBe($candidate->id)
        ->and($vote->cast_by)->toBe($user->id);

    // Open ballots show no tallies and no ballot form after voting.
    $this->get(route('elections.show', $election))
        ->assertOk()->assertSee('Your school has voted')->assertDontSee('Cast vote')->assertSee('Results appear when voting closes.');
});

it('gives each school exactly one vote even with several staff or a repeat tap', function () {
    $school = memberSchool($this->org['kanoChapter']);
    $election = openElection($this->org);
    [$a, $b] = $election->candidates()->get();

    $this->actingAs(electionVoter($school));
    $this->post(route('elections.vote', $election), ['candidate_id' => $a->id])->assertSessionHasNoErrors();
    $this->post(route('elections.vote', $election), ['candidate_id' => $b->id])->assertSessionHasErrors('candidate_id');

    // A second proprietor of the same school cannot vote again either.
    $this->actingAs(electionVoter($school));
    $this->post(route('elections.vote', $election), ['candidate_id' => $b->id])->assertSessionHasErrors('candidate_id');

    expect(ElectionVote::query()->count())->toBe(1)
        ->and(ElectionVote::query()->sole()->candidate_id)->toBe($a->id);

    // The database itself refuses a duplicate ballot.
    expect(fn () => ElectionVote::query()->create([
        'election_id' => $election->id, 'candidate_id' => $b->id, 'school_id' => $school->id,
        'cast_by' => User::factory()->create()->id, 'cast_at' => now(),
    ]))->toThrow(UniqueConstraintViolationException::class);
});

it('lets the same school vote in different elections', function () {
    $school = memberSchool($this->org['kanoChapter']);
    $one = openElection($this->org);
    $two = openElection($this->org, ['title' => 'Secretary 2026']);

    $this->actingAs(electionVoter($school));
    $this->post(route('elections.vote', $one), ['candidate_id' => $one->candidates()->first()->id])->assertSessionHasNoErrors();
    $this->post(route('elections.vote', $two), ['candidate_id' => $two->candidates()->first()->id])->assertSessionHasNoErrors();

    expect(ElectionVote::query()->count())->toBe(2);
});

it('lets a user who runs two schools vote once per school', function () {
    $first = memberSchool($this->org['kanoChapter']);
    $second = memberSchool($this->org['kanoChapter']);
    $election = openElection($this->org);
    $candidate = $election->candidates()->first();

    $user = electionVoter($first);
    $secondAssignment = RoleAssignment::grant($user, Role::Proprietor, ScopeLevel::School, $second->id);

    $this->actingAs($user);
    $this->post(route('elections.vote', $election), ['candidate_id' => $candidate->id])->assertSessionHasNoErrors();

    // Switch role to the second school.
    $this->withSession([ActiveContext::SESSION_KEY => $secondAssignment->id])
        ->post(route('elections.vote', $election), ['candidate_id' => $candidate->id])
        ->assertSessionHasNoErrors();

    expect(ElectionVote::query()->pluck('school_id')->sort()->values()->all())
        ->toBe(collect([$first->id, $second->id])->sort()->values()->all());
});

it('rejects a candidate from another election', function () {
    $school = memberSchool($this->org['kanoChapter']);
    $election = openElection($this->org);
    $other = openElection($this->org, ['title' => 'Other']);

    $this->actingAs(electionVoter($school));
    $this->post(route('elections.vote', $election), ['candidate_id' => $other->candidates()->first()->id])->assertSessionHasErrors('candidate_id');
    $this->post(route('elections.vote', $election), [])->assertSessionHasErrors('candidate_id');

    expect(ElectionVote::query()->count())->toBe(0);
});

it('keeps outsiders, owing schools and closed ballots from voting', function () {
    $election = openElection($this->org);
    $candidate = $election->candidates()->first();

    // A school in another chapter.
    $this->actingAs(electionVoter(memberSchool($this->org['katsinaNorth'])));
    $this->post(route('elections.vote', $election), ['candidate_id' => $candidate->id])->assertForbidden();
    $this->get(route('elections.show', $election))->assertForbidden();

    // A school that owes dues.
    $owing = School::factory()->for($this->org['kanoChapter'])->status(MembershipStatus::Owing)->create();
    $this->actingAs(electionVoter($owing));
    $this->post(route('elections.vote', $election), ['candidate_id' => $candidate->id])->assertForbidden();

    // Closed by hand, or past its closing time.
    $school = memberSchool($this->org['kanoChapter']);
    $this->actingAs(electionVoter($school));
    $election->forceFill(['status' => Election::CLOSED])->save();
    $this->post(route('elections.vote', $election), ['candidate_id' => $candidate->id])->assertForbidden();

    $election->forceFill(['status' => Election::OPEN, 'closes_at' => now()->subMinute()])->save();
    $this->post(route('elections.vote', $election), ['candidate_id' => $candidate->id])->assertForbidden();

    // A draft is not even visible.
    $draft = openElection($this->org, ['title' => 'Not yet', 'status' => Election::DRAFT]);
    $this->get(route('elections.show', $draft))->assertForbidden();

    expect(ElectionVote::query()->count())->toBe(0);
});

it('lets a state-wide election be voted on by every school in the state', function () {
    $election = openElection($this->org, ['chapter_id' => null, 'title' => 'State chairman']);
    $candidate = $election->candidates()->first();

    $this->actingAs(electionVoter(memberSchool($this->org['kanoChapter'])));
    $this->post(route('elections.vote', $election), ['candidate_id' => $candidate->id])->assertSessionHasNoErrors();

    $this->actingAs(electionVoter(memberSchool($this->org['katsinaNorth'])));
    $this->post(route('elections.vote', $election), ['candidate_id' => $candidate->id])->assertForbidden();

    expect(ElectionVote::query()->count())->toBe(1);
});

it('keeps parents, bursars and staff out of the ballot', function (Role $role) {
    $election = openElection($this->org);
    $school = memberSchool($this->org['kanoChapter']);

    $this->actingAsRole($role, $role === Role::Parent ? null : $school->id);

    $this->get(route('elections.index'))->assertForbidden();
    $this->post(route('elections.vote', $election), ['candidate_id' => $election->candidates()->first()->id])->assertForbidden();
})->with([Role::Parent, Role::Bursar, Role::Staff]);

it('lets a chapter executive set up, open and close an election and publish the tally', function () {
    $schools = collect(range(1, 3))->map(fn () => memberSchool($this->org['kanoChapter']));
    $this->actingAsRole(Role::ChapterExecutive, $this->org['kanoChapter']->id);

    $this->get(route('elections.create'))->assertOk()->assertSee('Kano Chapter');

    // The executive may only run elections for their own chapter.
    $this->post(route('elections.store'), ['title' => 'x', 'area' => 'chapter:'.$this->org['katsinaNorth']->id])->assertSessionHasErrors('area');

    $this->post(route('elections.store'), [
        'title' => 'Chairman 2026',
        'area' => 'chapter:'.$this->org['kanoChapter']->id,
        'closes_at' => now()->addWeek()->format('Y-m-d\TH:i'),
    ])->assertRedirect();

    $election = Election::query()->sole();
    expect($election->isDraft())->toBeTrue()
        ->and($election->chapter_id)->toBe($this->org['kanoChapter']->id)
        ->and($election->state_id)->toBe($this->org['kano']->id);

    // Cannot open with fewer than two candidates.
    $this->post(route('elections.open', $election))->assertSessionHasErrors('election');
    $this->post(route('elections.candidates.store', $election), ['name' => 'Candidate A', 'school_id' => $schools[0]->id, 'statement' => 'Vote A'])->assertRedirect();
    $this->post(route('elections.candidates.store', $election), ['name' => 'Candidate B'])->assertRedirect();
    $this->get(route('elections.edit', $election))->assertOk()->assertSee('Candidate A')->assertSee('Open voting');
    $this->post(route('elections.open', $election))->assertRedirect(route('elections.show', $election));
    expect($election->fresh()->isOpen())->toBeTrue();

    // Candidates are locked once voting opens.
    $this->post(route('elections.candidates.store', $election), ['name' => 'Latecomer'])->assertSessionHasErrors('name');
    expect($election->candidates()->count())->toBe(2);

    [$a, $b] = $election->candidates()->get();
    foreach ([[$schools[0], $a], [$schools[1], $a], [$schools[2], $b]] as [$school, $candidate]) {
        $this->actingAs(electionVoter($school))->post(route('elections.vote', $election), ['candidate_id' => $candidate->id])->assertSessionHasNoErrors();
    }

    // While open: turnout and who voted, but no tallies and no choices.
    $this->actingAsRole(Role::ChapterExecutive, $this->org['kanoChapter']->id);
    $this->get(route('elections.show', $election))
        ->assertOk()->assertSee($schools[0]->name)->assertSee('Close voting')->assertSee('Results appear when voting closes.')->assertDontSee('Winner')->assertDontSee('67%');

    $this->post(route('elections.close', $election))->assertRedirect();

    $page = $this->get(route('elections.show', $election))->assertOk()->assertSee('Results')->assertSee('Winner');
    $page->assertSeeInOrder(['Candidate A', '2 · 67%', 'Candidate B', '1 · 33%']);

    // Voters see the result too, once closed.
    $this->actingAs(electionVoter($schools[0]))->get(route('elections.show', $election))->assertOk()->assertSee('Candidate A')->assertSee('67%');
});

it('stops executives running elections outside their own chapter or state', function () {
    $election = openElection($this->org);

    $this->actingAsRole(Role::ChapterExecutive, $this->org['katsinaNorth']->id);
    $this->get(route('elections.show', $election))->assertForbidden();
    $this->post(route('elections.close', $election))->assertForbidden();
    $this->get(route('elections.index'))->assertOk()->assertDontSee('Chairman 2026');

    $this->actingAsRole(Role::StateExecutive, $this->org['kano']->id);
    $this->get(route('elections.show', $election))->assertOk();

    $this->actingAsRole(Role::StateExecutive, $this->org['katsina']->id);
    $this->get(route('elections.show', $election))->assertForbidden();
});

it('puts Elections in the navigation for executives and proprietors', function () {
    $school = memberSchool($this->org['kanoChapter']);

    $this->actingAsRole(Role::ChapterExecutive, $this->org['kanoChapter']->id);
    $this->get('/dashboard')->assertSee(route('elections.index'));

    $this->actingAs(electionVoter($school));
    $this->get('/dashboard')->assertSee(route('elections.index'));

    $this->actingAsRole(Role::Parent);
    $this->get('/dashboard')->assertDontSee(route('elections.index'));
});
