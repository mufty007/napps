<?php

namespace Modules\Surveys\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Support\Scope\ActiveContext;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Surveys\Models\Survey;
use Modules\Surveys\Models\SurveyQuestion;
use Modules\Surveys\Models\SurveyResponse;

/**
 * School side: a proprietor answers a survey once on behalf of the school.
 */
class SurveyRespondController extends Controller
{
    public function __construct(private readonly ActiveContext $context) {}

    public function index(): View
    {
        Gate::authorize('surveys.respond');
        $school = $this->school();

        $surveys = Survey::query()->openFor($school)->withCount('questions')->orderBy('closes_at')->latest()->get();
        $answered = SurveyResponse::query()
            ->where('school_id', $school->id)
            ->pluck('submitted_at', 'survey_id');

        return view('surveys::respond.index', compact('surveys', 'answered', 'school'));
    }

    public function show(Survey $survey): View|RedirectResponse
    {
        Gate::authorize('surveys.respond');
        $school = $this->school();
        $this->ensureAvailable($survey, $school);

        if ($this->alreadyAnswered($survey, $school)) {
            return redirect()->route('surveys.respond.index')
                ->with('status', __('Your school has already answered this survey. Thank you.'));
        }

        return view('surveys::respond.show', [
            'survey' => $survey,
            'questions' => $survey->questions()->get(),
            'school' => $school,
        ]);
    }

    public function store(Request $request, Survey $survey): RedirectResponse
    {
        Gate::authorize('surveys.respond');
        $school = $this->school();
        $this->ensureAvailable($survey, $school);

        if ($this->alreadyAnswered($survey, $school)) {
            return redirect()->route('surveys.respond.index')
                ->with('status', __('Your school has already answered this survey. Thank you.'));
        }

        $questions = $survey->questions()->get();
        $request->validate($this->rules($questions), [], $this->attributes($questions));

        /** @var array<int|string, mixed> $input */
        $input = (array) $request->input('answers', []);

        try {
            DB::transaction(function () use ($survey, $school, $request, $questions, $input) {
                $response = SurveyResponse::query()->create([
                    'survey_id' => $survey->id,
                    'user_id' => $request->user()->id,
                    'school_id' => $school->id,
                    'submitted_at' => now(),
                ]);

                foreach ($questions as $question) {
                    $value = $input[$question->id] ?? null;

                    if ($value === null || trim((string) $value) === '') {
                        continue;
                    }

                    $response->answers()->create([
                        'question_id' => $question->id,
                        'value' => trim((string) $value),
                    ]);
                }
            });
        } catch (UniqueConstraintViolationException) {
            return redirect()->route('surveys.respond.index')
                ->with('status', __('Your school has already answered this survey. Thank you.'));
        }

        return redirect()->route('surveys.respond.index')->with('status', __('Thank you. Your answers have been submitted.'));
    }

    /**
     * @param  iterable<SurveyQuestion>  $questions
     * @return array<string, list<mixed>>
     */
    private function rules(iterable $questions): array
    {
        $rules = ['answers' => ['nullable', 'array']];

        foreach ($questions as $question) {
            $presence = $question->required ? 'required' : 'nullable';

            $rules['answers.'.$question->id] = match ($question->type) {
                'mc' => [$presence, 'string', Rule::in($question->choices())],
                'scale' => [$presence, 'integer', 'between:'.implode(',', $question->scaleRange())],
                'number' => [$presence, 'numeric', 'between:-999999999999,999999999999'],
                default => [$presence, 'string', 'max:2000'],
            };
        }

        return $rules;
    }

    /**
     * @param  iterable<SurveyQuestion>  $questions
     * @return array<string, string>
     */
    private function attributes(iterable $questions): array
    {
        $names = [];

        foreach ($questions as $question) {
            $names['answers.'.$question->id] = '"'.$question->prompt.'"';
        }

        return $names;
    }

    private function school(): School
    {
        $id = $this->context->schoolId();
        abort_if($id === null, 403, __('Switch to a school role to answer surveys.'));

        return School::query()->findOrFail($id);
    }

    private function ensureAvailable(Survey $survey, School $school): void
    {
        abort_unless($survey->isOpen() && $survey->targets($school), 404);
    }

    private function alreadyAnswered(Survey $survey, School $school): bool
    {
        return SurveyResponse::query()->where('survey_id', $survey->id)->where('school_id', $school->id)->exists();
    }
}
