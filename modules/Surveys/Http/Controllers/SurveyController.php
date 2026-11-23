<?php

namespace Modules\Surveys\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Scope\ActiveContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Surveys\Models\Survey;
use Modules\Surveys\Services\SurveyAudience;

/**
 * Executive side: build, publish and close surveys.
 */
class SurveyController extends Controller
{
    public function __construct(
        private readonly ActiveContext $context,
        private readonly SurveyAudience $audience,
    ) {}

    public function index(): View
    {
        Gate::authorize('viewAny', Survey::class);

        $surveys = Survey::query()
            ->visibleTo($this->context)
            ->with('creator:id,name')
            ->withCount(['questions', 'responses'])
            ->latest()
            ->paginate(15);

        return view('surveys::manage.index', compact('surveys'));
    }

    public function create(): View
    {
        Gate::authorize('create', Survey::class);

        return view('surveys::manage.form', [
            'survey' => new Survey(['status' => Survey::DRAFT]),
            'audiences' => $this->audience->options(),
            'audienceKey' => array_key_first($this->audience->options()),
            'questions' => collect(),
            'canEdit' => true,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Survey::class);

        $data = $this->validated($request);

        $survey = Survey::query()->create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'closes_at' => $data['closes_at'] ?? null,
            'status' => Survey::DRAFT,
            'created_by' => $request->user()->id,
            ...$this->audience->resolve($data['audience']),
        ]);

        activity('surveys')->performedOn($survey)->causedBy($request->user())->log('created');

        return redirect()->route('surveys.edit', $survey)
            ->with('status', __('Survey saved. Add your questions below, then publish it.'));
    }

    public function edit(Survey $survey): View
    {
        Gate::authorize('view', $survey);

        return view('surveys::manage.form', [
            'survey' => $survey,
            'audiences' => $this->audience->options(),
            'audienceKey' => $this->audience->keyFor($survey->scope_level, $survey->scope_id),
            'questions' => $survey->questions()->get(),
            'canEdit' => Gate::allows('update', $survey),
        ]);
    }

    public function update(Request $request, Survey $survey): RedirectResponse
    {
        Gate::authorize('update', $survey);

        $data = $this->validated($request, $survey);

        $survey->fill([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'closes_at' => $data['closes_at'] ?? null,
        ]);

        // The audience is fixed once people may have started answering.
        if ($survey->isDraft()) {
            $survey->fill($this->audience->resolve($data['audience']));
        }

        $survey->save();

        return redirect()->route('surveys.edit', $survey)->with('status', __('Survey updated.'));
    }

    public function destroy(Request $request, Survey $survey): RedirectResponse
    {
        Gate::authorize('delete', $survey);

        if (! $survey->isDraft() && $survey->responses()->withoutGlobalScopes()->exists()) {
            return back()->withErrors(['survey' => __('This survey already has responses. Close it instead of deleting it.')]);
        }

        activity('surveys')->performedOn($survey)->causedBy($request->user())->log('deleted');
        $survey->delete();

        return redirect()->route('surveys.index')->with('status', __('Survey deleted.'));
    }

    public function publish(Request $request, Survey $survey): RedirectResponse
    {
        Gate::authorize('update', $survey);

        if ($survey->isOpen()) {
            return back();
        }

        if (! $survey->questions()->exists()) {
            return back()->withErrors(['publish' => __('Add at least one question before publishing.')]);
        }

        if ($survey->closes_at !== null && $survey->closes_at->isPast()) {
            return back()->withErrors(['publish' => __('The closing date has passed. Set a new closing date first.')]);
        }

        $survey->forceFill(['status' => Survey::OPEN, 'opened_at' => $survey->opened_at ?? now()])->save();

        activity('surveys')->performedOn($survey)->causedBy($request->user())->log('published');

        return redirect()->route('surveys.edit', $survey)->with('status', __('Survey is open for responses.'));
    }

    public function close(Request $request, Survey $survey): RedirectResponse
    {
        Gate::authorize('update', $survey);

        $survey->forceFill(['status' => Survey::CLOSED])->save();

        activity('surveys')->performedOn($survey)->causedBy($request->user())->log('closed');

        return redirect()->route('surveys.edit', $survey)->with('status', __('Survey closed.'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Survey $survey = null): array
    {
        $rules = [
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            // A new survey must close in the future; an existing one keeps whatever it had.
            'closes_at' => $survey === null ? ['nullable', 'date', 'after:now'] : ['nullable', 'date'],
        ];

        if ($survey === null || $survey->isDraft()) {
            $rules['audience'] = ['required', 'string', Rule::in(array_keys($this->audience->options()))];
        }

        /** @var array<string, mixed> $data */
        $data = $request->validate($rules);

        return $data;
    }
}
