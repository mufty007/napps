<?php

namespace Modules\Announcements\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Announcements\Models\JobListing;

class JobBoardController extends Controller
{
    public function index(): View
    {
        $jobs = JobListing::query()
            ->open()
            ->with('school')
            ->latest('published_at')
            ->paginate(12);

        return view('announcements::jobs.index', compact('jobs'));
    }

    public function show(JobListing $job): View
    {
        abort_unless(
            $job->published_at
            && $job->published_at->lte(now())
            && ($job->closes_at === null || $job->closes_at->gte(now())),
            404
        );

        $job->loadMissing('school');

        return view('announcements::jobs.show', compact('job'));
    }

    public function manage(): View
    {
        Gate::authorize('jobs.manage');

        $jobs = JobListing::query()->with('school')->latest('published_at')->latest('id')->paginate(20);

        return view('announcements::jobs.manage', compact('jobs'));
    }

    public function create(): View
    {
        Gate::authorize('jobs.manage');

        return view('announcements::jobs.form', ['job' => new JobListing(['employment_type' => 'full_time'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('jobs.manage');

        $data = $this->validated($request);
        JobListing::query()->create([
            ...$data,
            'author_id' => $request->user()->id,
            'published_at' => $request->boolean('publish') ? now() : null,
        ]);

        return redirect()->route('jobs.manage')->with('status', __('Job listing saved.'));
    }

    public function edit(JobListing $job): View
    {
        Gate::authorize('jobs.manage');

        return view('announcements::jobs.form', compact('job'));
    }

    public function update(Request $request, JobListing $job): RedirectResponse
    {
        Gate::authorize('jobs.manage');

        $job->fill($this->validated($request));
        if ($request->boolean('publish') && ! $job->published_at) {
            $job->published_at = now();
        }
        if ($request->boolean('unpublish')) {
            $job->published_at = null;
        }
        $job->save();

        return redirect()->route('jobs.manage')->with('status', __('Job listing updated.'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'school_name' => ['nullable', 'string', 'max:180'],
            'location' => ['nullable', 'string', 'max:180'],
            'employment_type' => ['required', 'in:full_time,part_time,contract'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'summary' => ['nullable', 'string', 'max:1000'],
            'body' => ['required', 'string', 'max:20000'],
            'closes_at' => ['nullable', 'date', 'after:today'],
        ]);
    }
}
