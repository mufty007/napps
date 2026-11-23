<?php

use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Enums\ScopeLevel;
use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Community\Models\Conversation;
use Modules\Community\Models\Message;
use Modules\Community\Models\Poll;
use Modules\Community\Services\SpaceDirectory;

beforeEach(function () {
    $this->seedRoles();
    $this->org = membershipOrg();
    $this->kanoSpace = app(SpaceDirectory::class)->ensureChapterSpace($this->org['kanoChapter']);

    $this->amina = User::factory()->create(['name' => 'Amina Proprietor']);
    $this->aminaSchool = memberSchool($this->org['kanoChapter'], ['name' => 'Amina Academy'], $this->amina);
    $this->bello = User::factory()->create(['name' => 'Bello Proprietor']);
    memberSchool($this->org['kanoChapter'], ['name' => 'Bello College'], $this->bello);
    $this->exec = officer(Role::ChapterExecutive, $this->org['kanoChapter']->id);
});

function pollPost(array $overrides = []): array
{
    return [
        'space_id' => test()->kanoSpace->id,
        'type' => 'poll',
        'body' => 'When should the chapter meet?',
        'options' => ['Saturday morning', 'Sunday afternoon', ''],
        ...$overrides,
    ];
}

it('creates a quick poll from the composer, lets members vote and change their vote', function () {
    $this->actingAs($this->amina)->post(route('community.feed.store'), pollPost(['options' => ['Only one']]))
        ->assertSessionHasErrors('options');

    $this->post(route('community.feed.store'), pollPost())->assertRedirect();
    $poll = Poll::query()->with('options')->sole();
    [$saturday, $sunday] = $poll->options->all();

    expect($poll->options)->toHaveCount(2) // the blank row is dropped
        ->and($poll->formal)->toBeFalse();

    // Results stay hidden until you vote.
    $this->actingAs($this->bello)->get(route('community.feed'))->assertOk()
        ->assertSee('When should the chapter meet?')->assertSee('Saturday morning')->assertDontSee('Change vote');

    $this->postJson(route('community.polls.vote', $poll), ['options' => [$saturday->id]])
        ->assertOk()->assertJsonPath('status', 'Vote saved.');
    $this->postJson(route('community.polls.vote', $poll), ['options' => [$sunday->id]])->assertOk();
    $this->postJson(route('community.polls.vote', $poll), ['options' => [$saturday->id, $sunday->id]])
        ->assertStatus(422)->assertJsonValidationErrors('poll');

    expect($poll->votes()->pluck('option_id')->all())->toBe([$sunday->id]);

    $this->get(route('community.posts.show', $poll->post))->assertOk()->assertSee('Change vote')->assertSee('100%');

    // Only the author (or an official) can close it; then voting stops.
    $this->post(route('community.polls.close', $poll))->assertForbidden();
    $this->actingAs($this->amina)->post(route('community.polls.close', $poll))->assertRedirect();
    $this->actingAs($this->bello)->postJson(route('community.polls.vote', $poll), ['options' => [$saturday->id]])
        ->assertStatus(422)->assertJsonValidationErrors('poll');
});

it('runs a formal vote: executives only, one final ballot per member school, hidden until close, exportable (COM-4)', function () {
    // Members cannot open a formal vote.
    $this->actingAs($this->amina)->post(route('community.feed.store'), pollPost(['formal' => '1', 'closes_at' => now()->addDays(3)->format('Y-m-d\TH:i')]))
        ->assertSessionHasErrors('formal');

    // Executives must set a closing time.
    $this->actingAs($this->exec)->post(route('community.feed.store'), pollPost(['formal' => '1']))
        ->assertSessionHasErrors('closes_at');

    $this->post(route('community.feed.store'), pollPost([
        'body' => 'Motion: raise chapter dues to N20,000',
        'options' => ['For', 'Against', 'Abstain'],
        'formal' => '1',
        'multiple' => '1',
        'closes_at' => now()->addDays(3)->format('Y-m-d\TH:i'),
    ]))->assertRedirect();

    $poll = Poll::query()->with('options')->sole();
    [$for, $against] = $poll->options->all();
    expect($poll->formal)->toBeTrue()
        ->and($poll->multiple)->toBeFalse() // a ballot is always one choice
        ->and($poll->post->pinned)->toBeTrue();

    // A second proprietor of the same school cannot cast a second ballot.
    $colleague = User::factory()->create();
    RoleAssignment::grant($colleague, Role::Proprietor, ScopeLevel::School, $this->aminaSchool->id);

    $this->actingAs($this->amina)->postJson(route('community.polls.vote', $poll), ['options' => [$for->id]])->assertOk();
    $this->postJson(route('community.polls.vote', $poll), ['options' => [$against->id]])->assertStatus(422);
    $this->actingAs($colleague)->postJson(route('community.polls.vote', $poll), ['options' => [$against->id]])->assertStatus(422);

    // Results are hidden while open; turnout is visible.
    $this->actingAs($this->bello)->get(route('community.posts.show', $poll->post))->assertOk()
        ->assertSee('Results are hidden until voting closes.')->assertSee('Turnout: 1 of 2 member schools');
    $this->postJson(route('community.polls.vote', $poll), ['options' => [$against->id]])->assertOk();

    // Schools that owe dues cannot vote; neither can non-proprietors.
    $owing = User::factory()->create();
    memberSchool($this->org['kanoChapter'], ['membership_status' => MembershipStatus::Owing], $owing);
    $this->actingAs($owing)->postJson(route('community.polls.vote', $poll), ['options' => [$for->id]])->assertStatus(422);
    $this->actingAs($this->exec)->postJson(route('community.polls.vote', $poll), ['options' => [$for->id]])->assertStatus(422);

    // Export only after close, and only for officials.
    $this->get(route('community.polls.export', $poll))->assertForbidden();
    $this->post(route('community.polls.close', $poll))->assertRedirect();
    $this->actingAs($this->amina)->get(route('community.polls.export', $poll))->assertForbidden();

    $csv = $this->actingAs($this->exec)->get(route('community.polls.export', $poll))->assertOk()->streamedContent();
    expect($csv)->toContain('Motion: raise chapter dues')->toContain('For,1')->toContain('Against,1')
        ->toContain('Amina Academy')->toContain('Bello College');

    // Locked once closed.
    $this->actingAs($this->bello)->postJson(route('community.polls.vote', $poll), ['options' => [$for->id]])->assertStatus(422);
    expect($poll->votes()->count())->toBe(2);
});

it('sends chat photos privately to conversation members only (COM-3)', function () {
    Storage::fake('local');
    $this->withoutVite();

    $conversation = Conversation::query()->create(['type' => 'direct', 'created_by' => $this->amina->id]);
    $conversation->members()->attach([$this->amina->id, $this->bello->id]);

    $this->actingAs($this->amina)->postJson(route('community.chat.messages', $conversation), [])
        ->assertStatus(422)->assertJsonValidationErrors('body');
    $this->postJson(route('community.chat.messages', $conversation), ['photo' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')])
        ->assertStatus(422)->assertJsonValidationErrors('photo');

    $response = $this->post(route('community.chat.messages', $conversation), [
        'photo' => UploadedFile::fake()->image('classroom.png', 1600, 1200),
    ], ['Accept' => 'application/json'])->assertOk();

    $message = Message::query()->sole();
    expect($message->body)->toBe('')
        ->and($message->getFirstMedia('photo'))->not->toBeNull()
        ->and($response->json('html'))->toContain(route('community.chat.photo', [$conversation, $message]));

    $this->actingAs($this->bello)->get(route('community.chat.photo', [$conversation, $message]))->assertOk();
    $this->get(route('community.chat.show', $conversation))->assertOk()->assertSee(route('community.chat.photo', [$conversation, $message]), false);
    $this->get(route('community.chat.index'))->assertOk()->assertSee('Photo');

    $outsider = User::factory()->create();
    memberSchool($this->org['kanoChapter'], [], $outsider);
    $this->actingAs($outsider)->get(route('community.chat.photo', [$conversation, $message]))->assertNotFound();
});
