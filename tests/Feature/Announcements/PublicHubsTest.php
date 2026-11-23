<?php

use App\Enums\Role;
use App\Models\Chapter;
use App\Models\User;
use Modules\Announcements\Models\Event;
use Modules\Announcements\Models\JobListing;
use Modules\Announcements\Models\NewsPost;

it('serves the public hubs', function (string $uri, string $text) {
    $this->get($uri)->assertOk()->assertSee($text);
})->with([
    ['/news', 'News & updates'],
    ['/events', 'Events & webinars'],
    ['/jobs', 'Job board'],
    ['/join', 'Join your state hub'],
]);

it('shows published news, events and jobs', function () {
    $author = User::factory()->create();

    $news = NewsPost::query()->create([
        'title' => 'Chapter circular on dues',
        'slug' => 'chapter-circular-dues',
        'body' => 'Please pay before the end of term.',
        'audience' => 'public',
        'level' => 'national',
        'author_id' => $author->id,
        'published_at' => now(),
    ]);

    $event = Event::query()->create([
        'title' => 'National AGM webinar',
        'slug' => 'national-agm-webinar',
        'kind' => 'webinar',
        'starts_at' => now()->addWeek(),
        'audience' => 'public',
        'level' => 'national',
        'author_id' => $author->id,
        'published_at' => now(),
    ]);

    $job = JobListing::query()->create([
        'title' => 'Science teacher',
        'slug' => 'science-teacher-demo',
        'school_name' => 'Demo Academy',
        'body' => 'Teach JS 1–3 sciences.',
        'author_id' => $author->id,
        'published_at' => now(),
    ]);

    $this->get(route('news.show', $news))->assertOk()->assertSee('Chapter circular on dues');
    $this->get(route('events.show', $event))->assertOk()->assertSee('National AGM webinar');
    $this->get(route('jobs.show', $job))->assertOk()->assertSee('Science teacher');
});

it('lets executives publish news and chapter executives add parents', function () {
    $this->actingAsRole(Role::NationalExecutive);

    $this->post(route('news.store'), [
        'title' => 'Secretariat update',
        'body' => 'Platform is live for Kano and Katsina chapters.',
        'audience' => 'public',
        'level' => 'national',
        'publish' => '1',
    ])->assertRedirect(route('news.manage'));

    expect(NewsPost::query()->where('title', 'Secretariat update')->exists())->toBeTrue();

    $chapter = Chapter::factory()->create(['name' => 'Test Chapter']);
    $this->actingAsRole(Role::ChapterExecutive, $chapter->id);

    $this->post(route('membership.parents.store'), [
        'name' => 'Hauwa Parent',
        'phone' => '08035551234',
        'notes' => 'Guardian at demo school',
    ])->assertRedirect(route('membership.parents.index'));

    $parent = User::query()->where('phone', '+2348035551234')->first();
    expect($parent)->not->toBeNull()
        ->and($parent->holdsRole(Role::Parent))->toBeTrue();
});
