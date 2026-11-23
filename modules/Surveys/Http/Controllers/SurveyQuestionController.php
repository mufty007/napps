<?php

namespace Modules\Surveys\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Surveys\Models\Survey;
use Modules\Surveys\Models\SurveyQuestion;

/**
 * The builder: add, remove and reorder questions while a survey is a draft.
 */
class SurveyQuestionController extends Controller
{
    public function store(Request $request, Survey $survey): RedirectResponse
    {
        Gate::authorize('update', $survey);
        $this->ensureDraft($survey);

        $data = $request->validate([
            'type' => ['required', Rule::in(SurveyQuestion::TYPES)],
            'prompt' => ['required', 'string', 'max:500'],
            'required' => ['nullable', 'boolean'],
            'choices' => ['nullable', 'string', 'max:3000'],
            'scale_min' => ['nullable', 'integer', 'in:0,1'],
            'scale_max' => ['nullable', 'integer', 'between:3,10'],
            'min_label' => ['nullable', 'string', 'max:60'],
            'max_label' => ['nullable', 'string', 'max:60'],
        ]);

        $options = match ($data['type']) {
            'mc' => ['choices' => $this->parseChoices($data['choices'] ?? '')],
            'scale' => [
                'min' => (int) ($data['scale_min'] ?? SurveyQuestion::DEFAULT_SCALE[0]),
                'max' => (int) ($data['scale_max'] ?? SurveyQuestion::DEFAULT_SCALE[1]),
                'min_label' => $data['min_label'] ?? null,
                'max_label' => $data['max_label'] ?? null,
            ],
            default => null,
        };

        $survey->questions()->create([
            'type' => $data['type'],
            'prompt' => trim($data['prompt']),
            'options' => $options,
            'required' => $request->boolean('required'),
            'sort' => (int) $survey->questions()->max('sort') + 1,
        ]);

        return redirect()->route('surveys.edit', $survey)->withFragment('questions')->with('status', __('Question added.'));
    }

    public function destroy(Survey $survey, SurveyQuestion $question): RedirectResponse
    {
        Gate::authorize('update', $survey);
        $this->ensureDraft($survey);
        abort_unless($question->survey_id === $survey->id, 404);

        $question->delete();

        return redirect()->route('surveys.edit', $survey)->withFragment('questions')->with('status', __('Question removed.'));
    }

    public function move(Request $request, Survey $survey, SurveyQuestion $question): RedirectResponse
    {
        Gate::authorize('update', $survey);
        $this->ensureDraft($survey);
        abort_unless($question->survey_id === $survey->id, 404);

        $direction = $request->validate(['direction' => ['required', 'in:up,down']])['direction'];

        $ids = $survey->questions()->pluck('id')->all();
        $index = array_search($question->id, $ids, true);
        $swap = $direction === 'up' ? $index - 1 : $index + 1;

        if ($index !== false && isset($ids[$swap])) {
            [$ids[$index], $ids[$swap]] = [$ids[$swap], $ids[$index]];
        }

        foreach ($ids as $position => $id) {
            SurveyQuestion::query()->whereKey($id)->update(['sort' => $position + 1]);
        }

        return redirect()->route('surveys.edit', $survey)->withFragment('questions');
    }

    /**
     * Questions are locked once a survey has been published, so every
     * response answers the same sheet.
     */
    private function ensureDraft(Survey $survey): void
    {
        if (! $survey->isDraft()) {
            throw ValidationException::withMessages([
                'prompt' => __('Questions cannot be changed after a survey is published.'),
            ]);
        }
    }

    /**
     * One choice per line, 2 to 12 distinct options.
     *
     * @return list<string>
     */
    private function parseChoices(string $text): array
    {
        $choices = collect(preg_split('/\R/', $text) ?: [])
            ->map(fn (string $line) => trim($line))
            ->filter(fn (string $line) => $line !== '')
            ->map(fn (string $line) => mb_substr($line, 0, 120))
            ->unique()
            ->values();

        if ($choices->count() < 2 || $choices->count() > 12) {
            throw ValidationException::withMessages([
                'choices' => __('Give between 2 and 12 choices, one per line.'),
            ]);
        }

        return $choices->all();
    }
}
