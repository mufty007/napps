<?php

namespace Modules\Surveys\Services;

use App\Models\Chapter;
use App\Models\School;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Surveys\Models\Survey;
use Modules\Surveys\Models\SurveyAnswer;
use Modules\Surveys\Models\SurveyQuestion;
use Modules\Surveys\Models\SurveyResponse;

/**
 * Aggregates a survey's answers. Every query runs through the school-owned
 * scope, so the numbers an executive sees only cover their own slice.
 */
class SurveyResults
{
    /**
     * @return Builder<SurveyResponse>
     */
    public function responses(Survey $survey, ?int $stateId = null, ?int $chapterId = null): Builder
    {
        return SurveyResponse::query()
            ->where('survey_id', $survey->id)
            ->when($stateId, fn (Builder $q) => $q->whereIn('school_id', School::withoutGlobalScopes()->select('id')->where('state_id', $stateId)))
            ->when($chapterId, fn (Builder $q) => $q->whereIn('school_id', School::withoutGlobalScopes()->select('id')->where('chapter_id', $chapterId)));
    }

    /**
     * Response rate per chapter (and rolled up per state) for the survey's audience.
     *
     * @return array<string, mixed> chapters and states (collections of rows), schools and responded (counts)
     */
    public function coverage(Survey $survey, ?int $stateId = null, ?int $chapterId = null): array
    {
        $schools = $survey->eligibleSchools()
            ->when($stateId, fn (Builder $q) => $q->where('state_id', $stateId))
            ->when($chapterId, fn (Builder $q) => $q->where('chapter_id', $chapterId))
            ->get(['id', 'chapter_id', 'state_id']);

        $respondedIds = $this->responses($survey, $stateId, $chapterId)->pluck('school_id')->filter()->flip();

        $chapterNames = Chapter::query()->with('state:id,name')
            ->whereIn('id', $schools->pluck('chapter_id')->unique())
            ->get()
            ->keyBy('id');

        $chapters = $schools->groupBy('chapter_id')->map(function (Collection $group, $chapterId) use ($chapterNames, $respondedIds) {
            $chapter = $chapterNames->first(fn (Chapter $c) => $c->id === (int) $chapterId);
            $responded = $group->filter(fn (School $s) => $respondedIds->has($s->id))->count();

            return [
                'chapter_id' => (int) $chapterId,
                'state_id' => $chapter instanceof Chapter ? $chapter->state_id : null,
                'chapter' => $chapter instanceof Chapter ? $chapter->name : __('Unassigned'),
                'state' => $chapter instanceof Chapter ? $chapter->state->name : '—',
                'schools' => $group->count(),
                'responded' => $responded,
                'rate' => $group->count() > 0 ? (int) round($responded / $group->count() * 100) : 0,
            ];
        })->sortBy([['state', 'asc'], ['chapter', 'asc']])->values();

        $states = $chapters->groupBy('state')->map(function (Collection $group, $state) {
            $total = $group->sum('schools');
            $responded = $group->sum('responded');

            return [
                'state' => (string) $state,
                'schools' => $total,
                'responded' => $responded,
                'rate' => $total > 0 ? (int) round($responded / $total * 100) : 0,
            ];
        })->values();

        return [
            'chapters' => $chapters,
            'states' => $states,
            'schools' => $schools->count(),
            'responded' => $respondedIds->count(),
        ];
    }

    /**
     * One summary per question, keyed by question id.
     *
     * @param  Collection<int, SurveyQuestion>  $questions
     * @return array<int, array<string, mixed>>
     */
    public function summarise(Survey $survey, Collection $questions, ?int $stateId = null, ?int $chapterId = null): array
    {
        $answers = SurveyAnswer::query()
            ->whereIn('response_id', $this->responses($survey, $stateId, $chapterId)->select('id'))
            ->get(['id', 'response_id', 'question_id', 'value'])
            ->groupBy('question_id');

        $summaries = [];

        foreach ($questions as $question) {
            $values = $answers->get($question->id, collect())
                ->pluck('value')
                ->filter(fn ($v) => $v !== null && $v !== '')
                ->values();

            $summaries[$question->id] = match ($question->type) {
                'mc' => $this->choices($question, $values),
                'scale' => $this->scale($question, $values),
                'number' => $this->number($values),
                default => ['count' => $values->count(), 'answers' => $values->reverse()->take(25)->values()->all()],
            };
        }

        return $summaries;
    }

    /**
     * @param  Collection<int, string>  $values
     * @return array<string, mixed>
     */
    private function choices(SurveyQuestion $question, Collection $values): array
    {
        $counts = $values->countBy();
        $total = $values->count();

        return [
            'count' => $total,
            'rows' => collect($question->choices())->map(fn (string $choice) => [
                'label' => $choice,
                'count' => (int) ($counts[$choice] ?? 0),
                'percent' => $total > 0 ? (int) round(($counts[$choice] ?? 0) / $total * 100) : 0,
            ])->all(),
        ];
    }

    /**
     * @param  Collection<int, string>  $values
     * @return array<string, mixed>
     */
    private function scale(SurveyQuestion $question, Collection $values): array
    {
        [$min, $max] = $question->scaleRange();
        $counts = $values->countBy();
        $total = $values->count();

        return [
            'count' => $total,
            'average' => $total > 0 ? round($values->avg(fn ($v) => (float) $v), 2) : null,
            'rows' => collect(range($min, $max))->map(fn (int $point) => [
                'label' => (string) $point,
                'count' => (int) ($counts[(string) $point] ?? 0),
                'percent' => $total > 0 ? (int) round(($counts[(string) $point] ?? 0) / $total * 100) : 0,
            ])->all(),
        ];
    }

    /**
     * @param  Collection<int, string>  $values
     * @return array<string, mixed>
     */
    private function number(Collection $values): array
    {
        $numbers = $values->map(fn ($v) => (float) $v);

        return [
            'count' => $numbers->count(),
            'average' => $numbers->isNotEmpty() ? round($numbers->avg(), 2) : null,
            'min' => $numbers->min(),
            'max' => $numbers->max(),
            'sum' => $numbers->isNotEmpty() ? $numbers->sum() : null,
        ];
    }
}
