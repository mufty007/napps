<?php

namespace Modules\Announcements\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Announcements\Models\NewsPost;

class NewsController extends Controller
{
    public function index(): View
    {
        $posts = NewsPost::query()
            ->published()
            ->where('audience', 'public')
            ->latest('published_at')
            ->paginate(12);

        return view('announcements::news.index', compact('posts'));
    }

    public function show(NewsPost $news): View
    {
        abort_unless($news->isPublished() && $news->audience === 'public', 404);

        return view('announcements::news.show', ['post' => $news]);
    }

    public function manage(): View
    {
        Gate::authorize('announcements.send');

        $posts = NewsPost::query()->latest('published_at')->latest('id')->paginate(20);

        return view('announcements::news.manage', compact('posts'));
    }

    public function create(): View
    {
        Gate::authorize('announcements.send');

        return view('announcements::news.form', ['post' => new NewsPost]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('announcements.send');

        $data = $this->validated($request);
        $post = NewsPost::query()->create([
            ...$data,
            'author_id' => $request->user()->id,
            'published_at' => $request->boolean('publish') ? now() : null,
        ]);

        return redirect()->route('news.manage')->with('status', __('News post saved.'));
    }

    public function edit(NewsPost $news): View
    {
        Gate::authorize('announcements.send');

        return view('announcements::news.form', ['post' => $news]);
    }

    public function update(Request $request, NewsPost $news): RedirectResponse
    {
        Gate::authorize('announcements.send');

        $news->fill($this->validated($request));
        if ($request->boolean('publish') && ! $news->published_at) {
            $news->published_at = now();
        }
        if ($request->boolean('unpublish')) {
            $news->published_at = null;
        }
        $news->save();

        return redirect()->route('news.manage')->with('status', __('News post updated.'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string', 'max:20000'],
            'audience' => ['required', 'in:public,members,parents'],
            'level' => ['required', 'in:national,state,chapter'],
        ]);
    }
}
