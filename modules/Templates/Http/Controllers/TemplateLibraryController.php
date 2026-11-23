<?php

namespace Modules\Templates\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Templates\Models\DocumentTemplate;
use Modules\Templates\Services\TemplateService;

class TemplateLibraryController extends Controller
{
    public function __construct(private readonly TemplateService $templates) {}

    public function index(Request $request): View
    {
        $this->authorizeAccess();

        $visible = fn () => DocumentTemplate::query()
            ->when(! $this->canPublish(), fn ($q) => $q->published());

        $category = $request->query('category');
        $category = in_array($category, DocumentTemplate::CATEGORIES, true) ? $category : null;

        $counts = $visible()->selectRaw('category, count(*) as total')->groupBy('category')->pluck('total', 'category');

        $templates = $visible()
            ->when($category, fn ($q) => $q->where('category', $category))
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        return view('templates::library.index', [
            'templates' => $templates,
            'category' => $category,
            'counts' => $counts,
            'total' => $counts->sum(),
            'canPublish' => $this->canPublish(),
        ]);
    }

    public function show(DocumentTemplate $template): View
    {
        $this->authorizeAccess();
        $this->ensureVisible($template);

        return view('templates::library.show', [
            'template' => $template->load(['reviewer', 'creator']),
            'preview' => $this->templates->renderHtml($template, [], preview: true),
            'fields' => $this->templates->fields($template),
            'canPublish' => $this->canPublish(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('templates.publish');

        return view('templates::library.form', [
            'template' => new DocumentTemplate(['category' => 'general']),
            'fields' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('templates.publish');

        $data = $this->validated($request, creating: true);

        $template = DocumentTemplate::query()->create([
            ...$data,
            'placeholders' => $this->templates->extractPlaceholders($data['body_html']),
            'version' => 1,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('templates.show', $template)
            ->with('status', __('Template saved as a draft. Have it legally reviewed, then publish it.'));
    }

    public function edit(DocumentTemplate $template): View
    {
        Gate::authorize('templates.publish');

        return view('templates::library.form', [
            'template' => $template,
            'fields' => $this->templates->fields($template),
        ]);
    }

    public function update(Request $request, DocumentTemplate $template): RedirectResponse
    {
        Gate::authorize('templates.publish');

        $data = $this->validated($request);
        $bodyChanged = trim($data['body_html']) !== trim($template->body_html);

        $template->fill($data);
        $template->placeholders = $this->templates->extractPlaceholders($data['body_html']);

        if ($bodyChanged) {
            // New wording means a new version that no lawyer has signed off yet.
            $template->version++;
            $template->legally_reviewed_at = null;
            $template->reviewed_by = null;
            $template->published_at = null;
        }

        $template->save();

        return redirect()->route('templates.show', $template)->with('status', $bodyChanged
            ? __('Saved as version :v. It was unpublished until it is legally reviewed again.', ['v' => $template->version])
            : __('Template details updated.'));
    }

    public function publish(Request $request, DocumentTemplate $template): RedirectResponse
    {
        Gate::authorize('templates.publish');

        if ($request->boolean('unpublish')) {
            $template->forceFill(['published_at' => null])->save();

            return back()->with('status', __('Template withdrawn from the library.'));
        }

        if (! $template->isReviewed()) {
            return back()->withErrors(['publish' => __('Only legally reviewed templates can be published.')]);
        }

        $template->forceFill(['published_at' => $template->published_at ?? now()])->save();

        return back()->with('status', __('Template published to the library.'));
    }

    public function review(Request $request, DocumentTemplate $template): RedirectResponse
    {
        Gate::authorize('templates.publish');

        if ($request->boolean('clear')) {
            $template->forceFill([
                'legally_reviewed_at' => null,
                'reviewed_by' => null,
                'published_at' => null,
            ])->save();

            return back()->with('status', __('Legal review cleared. The template was unpublished.'));
        }

        $template->forceFill([
            'legally_reviewed_at' => now(),
            'reviewed_by' => $request->user()->id,
        ])->save();

        return back()->with('status', __('Marked as legally reviewed.'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $creating = false): array
    {
        return $request->validate([
            'slug' => $creating
                ? ['required', 'string', 'max:80', 'regex:/^[a-z0-9_]+$/', Rule::unique('document_templates', 'slug')]
                : ['prohibited'],
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:1000'],
            'category' => ['required', Rule::in(DocumentTemplate::CATEGORIES)],
            'body_html' => ['required', 'string', 'max:200000'],
        ]);
    }

    private function authorizeAccess(): void
    {
        abort_unless(Gate::any(['templates.use', 'templates.publish']), 403);
    }

    private function canPublish(): bool
    {
        return Gate::allows('templates.publish');
    }

    private function ensureVisible(DocumentTemplate $template): void
    {
        abort_unless($template->isPublished() || $this->canPublish(), 404);
    }
}
