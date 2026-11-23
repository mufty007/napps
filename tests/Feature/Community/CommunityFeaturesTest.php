<?php

use App\Enums\Role;
use App\Enums\ScopeLevel;
use App\Models\RoleAssignment;
use App\Models\User;
use Modules\Community\Models\Conversation;
use Modules\Community\Models\Forum;
use Modules\Community\Models\Post;
use Modules\Community\Models\Report;
use Modules\Community\Models\Space;
use Modules\Community\Models\Thread;
use Modules\Community\Notifications\ChatMessageReceived;
use Modules\Community\Notifications\PostReplied;
use Modules\Community\Notifications\ThreadActivity;
use Modules\Community\Services\SpaceDirectory;

beforeEach(function () {
    $this->seedRoles();
    $this->org = membershipOrg();
    $this->kanoSpace = app(SpaceDirectory::class)->ensureChapterSpace($this->org['kanoChapter']);
    $this->katsinaSpace = app(SpaceDirectory::class)->ensureChapterSpace($this->org['katsinaNorth']);

    $this->amina = User::factory()->create(['name' => 'Amina Proprietor']);
    memberSchool($this->org['kanoChapter'], [], $this->amina);
    $this->bello = User::factory()->create(['name' => 'Bello Proprietor']);
    memberSchool($this->org['kanoChapter'], [], $this->bello);
    $this->daura = User::factory()->create(['name' => 'Daura Proprietor']);
    memberSchool($this->org['katsinaNorth'], [], $this->daura);
});

function freshDirectory(): SpaceDirectory
{
    app()->forgetScopedInstances();

    return app(SpaceDirectory::class);
}

it('builds each person\'s spaces from all their roles and gives every space the PRD forums', function () {
    $spaces = freshDirectory()->forViewer($this->amina);

    expect($spaces->pluck('type')->all())->toBe(['national', 'state', 'chapter'])
        ->and($spaces->pluck('name'))->toContain('Kano Chapter', 'Kano State')
        ->and($this->kanoSpace->forums()->pluck('name'))->toContain('Fee collection', 'Legal & claims', 'Government & regulation');

    // Parents and pending applicants are not members.
    expect(freshDirectory()->forViewer(User::factory()->create()))->toBeEmpty();
});

it('lets members post, comment and reply, and notifies the right people (COM-1)', function () {
    $this->actingAs($this->amina)->post(route('community.feed.store'), ['space_id' => $this->katsinaSpace->id, 'body' => 'Wrong chapter'])
        ->assertSessionHasErrors('space_id');

    $this->post(route('community.feed.store'), ['space_id' => $this->kanoSpace->id, 'body' => 'Who has a good instalment template?'])->assertRedirect();
    $post = Post::query()->sole();

    $this->actingAs($this->bello)->get(route('community.feed'))->assertOk()->assertSee('Who has a good instalment template?');
    $this->post(route('community.posts.comments', $post), ['body' => 'I use a 3-part plan.']);
    $comment = $post->comments()->sole();
    expect($this->amina->notifications()->where('type', PostReplied::class)->count())->toBe(1);

    $this->actingAs($this->amina)->post(route('community.posts.comments', $post), ['body' => 'Thanks!', 'parent_id' => $comment->id]);
    expect($post->comments()->where('parent_id', $comment->id)->count())->toBe(1)
        ->and($this->bello->notifications()->where('type', PostReplied::class)->count())->toBe(1);

    $this->get(route('community.posts.show', $post))->assertOk()->assertSee('I use a 3-part plan.')->assertSee('Thanks!');

    // Katsina members cannot see a Kano chapter post.
    $this->actingAs($this->daura)->get(route('community.posts.show', $post))->assertNotFound();
});

it('toggles likes over JSON and lets chapter executives pin official posts', function () {
    $post = Post::query()->create(['space_id' => $this->kanoSpace->id, 'author_id' => $this->amina->id, 'body' => 'AGM next week']);

    $this->actingAs($this->bello)->postJson(route('community.posts.react', $post))->assertJson(['liked' => true, 'count' => 1]);
    $this->postJson(route('community.posts.react', $post))->assertJson(['liked' => false, 'count' => 0]);

    $this->post(route('community.posts.pin', $post))->assertForbidden();
    $this->actingAs(officer(Role::ChapterExecutive, $this->org['kanoChapter']->id))->post(route('community.posts.pin', $post));
    expect($post->fresh()->pinned)->toBeTrue();
});

it('runs questions with answers, upvotes and a best answer (COM-2)', function () {
    $forum = Forum::query()->where('space_id', $this->kanoSpace->id)->where('name', 'Fee collection')->firstOrFail();

    $this->actingAs($this->amina)->post(route('community.forums.threads.store', $forum), [
        'title' => 'How do you handle half payments?', 'body' => 'Parents pay half and stop.', 'is_question' => 1,
    ]);
    $thread = Thread::query()->sole();

    $this->actingAs($this->bello)->post(route('community.forums.threads.reply', [$forum, $thread]), ['body' => 'Agree dates in writing at enrolment.']);
    $reply = $thread->replies()->sole();
    expect($this->amina->notifications()->where('type', ThreadActivity::class)->count())->toBe(1);

    $this->postJson(route('community.forums.replies.vote', [$forum, $thread, $reply]))->assertStatus(422); // own answer
    $this->actingAs($this->amina)->postJson(route('community.forums.replies.vote', [$forum, $thread, $reply]))->assertJson(['voted' => true, 'count' => 1]);

    $this->actingAs($this->bello)->post(route('community.forums.replies.best', [$forum, $thread, $reply]))->assertForbidden();
    $this->actingAs($this->amina)->post(route('community.forums.replies.best', [$forum, $thread, $reply]))->assertRedirect();

    expect($thread->fresh()->isAnswered())->toBeTrue()
        ->and($this->bello->notifications()->where('type', ThreadActivity::class)->count())->toBe(1);

    $this->get(route('community.forums.index', ['q' => 'half payments']))->assertSee('How do you handle half payments?');
    $this->get(route('community.forums.show', [$forum, 'filter' => 'answered']))->assertSee('How do you handle half payments?');
});

it('stops replies on a locked thread', function () {
    $forum = Forum::query()->where('space_id', $this->kanoSpace->id)->firstOrFail();
    $thread = Thread::query()->create(['forum_id' => $forum->id, 'author_id' => $this->amina->id, 'title' => 'Heated topic here', 'body' => 'Long debate']);
    $moderator = User::factory()->withTwoFactor()->create();
    RoleAssignment::grant($moderator, Role::Moderator, ScopeLevel::Chapter, $this->org['kanoChapter']->id);

    $this->actingAs($this->bello)->post(route('community.forums.threads.lock', [$forum, $thread]))->assertForbidden();
    $this->actingAs($moderator)->post(route('community.forums.threads.lock', [$forum, $thread]));
    $this->actingAs($this->bello)->post(route('community.forums.threads.reply', [$forum, $thread]), ['body' => 'One more thing'])->assertSessionHas('error');

    expect($thread->replies()->count())->toBe(0);
});

it('handles request-to-join and invite-only groups', function () {
    $this->actingAs($this->amina)->post(route('community.groups.store'), ['name' => 'Kano nursery proprietors', 'type' => 'interest', 'privacy' => 'request']);
    $group = Space::query()->where('type', 'interest')->sole();

    $this->actingAs($this->bello)->get(route('community.groups.show', $group))->assertOk()->assertSee('Members only');
    $this->post(route('community.groups.join', $group));
    expect(freshDirectory()->membershipStatus($this->bello, $group))->toBe('pending')
        ->and($this->amina->notifications()->count())->toBe(1);

    $this->actingAs($this->amina)->post(route('community.groups.respond', [$group, $this->bello]), ['decision' => 'approve']);
    expect(freshDirectory()->membershipStatus($this->bello, $group))->toBe('active');

    // Committees: executives only, invite-only and invisible to outsiders.
    $this->actingAs($this->amina)->post(route('community.groups.store'), ['name' => 'Finance committee', 'type' => 'committee', 'privacy' => 'open'])->assertForbidden();
    $exec = officer(Role::ChapterExecutive, $this->org['kanoChapter']->id);
    $this->actingAs($exec)->post(route('community.groups.store'), ['name' => 'Finance committee', 'type' => 'committee', 'privacy' => 'open']);
    $committee = Space::query()->where('type', 'committee')->sole();
    expect($committee->privacy)->toBe('invite');

    $this->actingAs($this->bello)->get(route('community.groups.show', $committee))->assertNotFound();
    $this->actingAs($exec)->post(route('community.groups.invite', $committee), ['user_id' => $this->bello->id]);
    $this->actingAs($this->bello)->post(route('community.groups.accept', $committee))->assertRedirect(route('community.groups.show', $committee));
    $this->get(route('community.groups.show', $committee))->assertOk()->assertSee('Finance committee');
});

it('keeps private chats private, updates live and sends one notice per chat (COM-3)', function () {
    $this->actingAs($this->amina)->post(route('community.chat.direct'), ['user_id' => $this->bello->id]);
    $chat = Conversation::query()->sole();

    $this->postJson(route('community.chat.messages', $chat), ['body' => 'Salaam Bello'])->assertOk()->assertJsonStructure(['id', 'html']);
    $this->postJson(route('community.chat.messages', $chat), ['body' => 'Are you at the AGM?']);

    expect($this->bello->unreadNotifications()->where('type', ChatMessageReceived::class)->count())->toBe(1);

    // An executive who is not in the chat cannot open it.
    $this->actingAs(officer(Role::NationalExecutive))->get(route('community.chat.show', $chat))->assertNotFound();

    $this->actingAs($this->bello)->getJson(route('community.chat.poll', [$chat, 'after' => 0]))
        ->assertOk()->assertSee('Are you at the AGM?', false);
    expect($this->bello->unreadNotifications()->count())->toBe(0);

    // Amina sees "Seen" up to Bello's read position.
    $this->actingAs($this->amina)->getJson(route('community.chat.poll', [$chat, 'after' => 999]))
        ->assertJson(['seen_up_to' => $chat->messages()->max('id')]);
});

it('opens the chapter channel for chapter members only', function () {
    $this->actingAs($this->amina)->get(route('community.chat.channel', $this->kanoSpace))->assertRedirect();
    $this->actingAs($this->daura)->get(route('community.chat.channel', $this->kanoSpace))->assertForbidden();

    $channel = Conversation::query()->where('type', 'channel')->sole();
    expect($channel->space_id)->toBe($this->kanoSpace->id);
});

it('routes reports to the moderators of that chapter (COM-5)', function () {
    $post = Post::query()->create(['space_id' => $this->kanoSpace->id, 'author_id' => $this->amina->id, 'body' => 'Buy cheap uniforms here!!!']);
    $this->actingAs($this->bello)->post(route('community.reports.store'), ['type' => 'post', 'id' => $post->id, 'reason' => 'spam']);
    $report = Report::query()->sole();

    $katsinaMod = User::factory()->withTwoFactor()->create();
    RoleAssignment::grant($katsinaMod, Role::Moderator, ScopeLevel::Chapter, $this->org['katsinaNorth']->id);
    $this->actingAs($katsinaMod)->get(route('community.moderation.index'))->assertOk()->assertDontSee('Buy cheap uniforms');
    $this->post(route('community.moderation.decide', $report), ['decision' => 'hide'])->assertForbidden();

    $kanoMod = User::factory()->withTwoFactor()->create();
    RoleAssignment::grant($kanoMod, Role::Moderator, ScopeLevel::Chapter, $this->org['kanoChapter']->id);
    $this->actingAs($kanoMod)->get(route('community.moderation.index'))->assertSee('Buy cheap uniforms');
    $this->post(route('community.moderation.decide', $report), ['decision' => 'hide']);

    expect($post->fresh()->hidden_at)->not->toBeNull()
        ->and($report->fresh()->status)->toBe('actioned');
    $this->assertDatabaseHas('activity_log', ['log_name' => 'moderation', 'subject_id' => $report->id, 'causer_id' => $kanoMod->id]);
});

it('sends community notices by SMS only when the person asks for it', function () {
    $post = Post::query()->create(['space_id' => $this->kanoSpace->id, 'author_id' => $this->amina->id, 'body' => 'Hello']);

    $this->actingAs($this->bello)->post(route('community.posts.comments', $post), ['body' => 'First']);
    expect(smsTo($this->amina))->toBe([]);

    $this->actingAs($this->amina)->put(route('notifications.preferences'), ['channels' => ['community.activity' => ['sms' => 1]]])->assertSessionHas('status');
    $this->actingAs($this->bello)->post(route('community.posts.comments', $post), ['body' => 'Second']);

    expect(smsTo($this->amina))->toHaveCount(1)
        ->and(smsTo($this->amina)[0])->toContain('commented on your post');
});

it('lands members on the Home feed and lists each community page in the sidebar', function () {
    $this->actingAs($this->amina)->get(route('start'))->assertRedirect(route('community.feed'));

    $this->get(route('community.feed'))->assertOk()
        ->assertSee('Start a post')
        ->assertSee(route('community.forums.index'), false)
        ->assertSee(route('community.groups.index'), false)
        ->assertSee(route('community.chat.index'), false)
        ->assertSee(route('dashboard'), false)
        ->assertDontSee('aria-label="Community"', false); // no tab bar any more

    // Parents have no community: they land on their dashboard.
    $this->actingAsRole(Role::Parent);
    $this->get(route('start'))->assertRedirect(route('dashboard'));
});

it('comments inline from the feed without leaving the page', function () {
    $post = Post::query()->create(['space_id' => $this->kanoSpace->id, 'author_id' => $this->amina->id, 'body' => 'Inline please']);

    $this->actingAs($this->bello)->postJson(route('community.posts.comments', $post), ['body' => 'Done inline'])
        ->assertOk()->assertJsonPath('html', fn (string $html) => str_contains($html, 'Done inline'));
    $comment = $post->comments()->sole();
    $this->postJson(route('community.posts.comments', $post), ['body' => 'A reply', 'parent_id' => $comment->id])->assertOk();

    $this->get(route('community.posts.comments.index', $post))->assertOk()->assertSee('Done inline')->assertSee('A reply');
    $this->actingAs($this->daura)->get(route('community.posts.comments.index', $post))->assertNotFound();
});
