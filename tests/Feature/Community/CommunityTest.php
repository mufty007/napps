<?php

use App\Enums\Role;
use App\Models\Chapter;
use App\Models\School;
use Modules\Community\Models\Post;
use Modules\Community\Models\Space;
use Modules\Community\Services\SpaceDirectory;

it('lets proprietors open the community feed and post to their chapter', function () {
    $school = School::factory()->create();
    $this->actingAsRole(Role::Proprietor, $school->id);

    $this->get(route('community.feed'))
        ->assertOk()
        ->assertSee('Start a post')
        ->assertSee('Forums')
        ->assertSee('Chat');

    $directory = app(SpaceDirectory::class);
    $space = $directory->ensureChapterSpace($school->chapter);

    $this->post(route('community.feed.store'), [
        'space_id' => $space->id,
        'body' => 'Hello chapter — sharing a fee tip.',
        'type' => 'text',
    ])->assertRedirect();

    expect(Post::query()->where('body', 'Hello chapter — sharing a fee tip.')->exists())->toBeTrue();
});

it('keeps parents out of community', function () {
    $this->actingAsRole(Role::Parent);

    $this->get(route('community.feed'))->assertForbidden();
    $this->get(route('community.forums.index'))->assertForbidden();
    $this->get(route('community.chat.index'))->assertForbidden();
});

it('opens a chapter chat channel for chapter executives', function () {
    $chapter = Chapter::factory()->create(['name' => 'Demo Chapter']);
    $this->actingAsRole(Role::ChapterExecutive, $chapter->id);

    $this->post(route('community.chat.chapter'))
        ->assertRedirect();

    $space = Space::query()->where('chapter_id', $chapter->id)->where('type', 'chapter')->first();
    expect($space)->not->toBeNull();
});
