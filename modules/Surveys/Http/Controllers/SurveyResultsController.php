<?php

namespace Modules\Surveys\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Surveys\Models\Survey;
use Modules\Surveys\Services\SurveyResults;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Results by chapter and state, plus a CSV export (one row per school).
 */
class SurveyResultsController extends Controller
{
    public function __construct(private readonly SurveyResults $results) {}

    public function show(Request $request, Survey $survey): View
    {
        Gate::authorize('view', $survey);
        [$stateId, $chapterId] = $this->filters($request);

        $questions = $survey->questions()->get();
        $coverage = $this->results->coverage($survey, $stateId, $chapterId);

        // Filter choices come from the unfiltered audience so people can switch freely.
        $all = $this->results->coverage($survey);

        return view('surveys::results.show', [
            'survey' => $survey,
            'questions' => $questions,
            'summaries' => $this->results->summarise($survey, $questions, $stateId, $chapterId),
            'coverage' => $coverage,
            'stateOptions' => $all['chapters']->pluck('state', 'state_id')->filter()->all(),
            'chapterOptions' => $all['chapters']->pluck('chapter', 'chapter_id')->all(),
            'stateId' => $stateId,
            'chapterId' => $chapterId,
        ]);
    }

    public function export(Request $request, Survey $survey): StreamedResponse
    {
        Gate::authorize('view', $survey);
        [$stateId, $chapterId] = $this->filters($request);

        $questions = $survey->questions()->get();
        $filename = 'survey-'.$survey->id.'-results.csv';

        return response()->streamDownload(function () use ($survey, $questions, $stateId, $chapterId) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel opens it correctly.

            fputcsv($out, $this->row([
                __('Response ID'), __('Submitted at'), __('State'), __('Chapter'), __('School'), __('Submitted by'),
                ...$questions->pluck('prompt')->all(),
            ]));

            $this->results->responses($survey, $stateId, $chapterId)
                ->with(['school.chapter.state', 'user:id,name', 'answers'])
                ->chunkById(200, function ($responses) use ($out, $questions) {
                    foreach ($responses as $response) {
                        $answers = $response->answers->pluck('value', 'question_id');

                        fputcsv($out, $this->row([
                            $response->id,
                            $response->submitted_at->format('Y-m-d H:i'),
                            $response->school?->chapter?->state?->name,
                            $response->school?->chapter?->name,
                            $response->school?->name,
                            $response->user?->name,
                            ...$questions->map(fn ($q) => $answers[$q->id] ?? '')->all(),
                        ]));
                    }
                });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{0: int|null, 1: int|null} */
    private function filters(Request $request): array
    {
        $data = $request->validate([
            'state_id' => ['nullable', 'integer'],
            'chapter_id' => ['nullable', 'integer'],
        ]);

        return [
            isset($data['state_id']) ? (int) $data['state_id'] : null,
            isset($data['chapter_id']) ? (int) $data['chapter_id'] : null,
        ];
    }

    /**
     * @param  list<mixed>  $cells
     * @return list<string>
     */
    private function row(array $cells): array
    {
        return array_map(fn (mixed $cell) => $this->safe($cell), $cells);
    }

    /** Neutralise spreadsheet formulas in exported text (CSV injection). */
    private function safe(mixed $value): string
    {
        $text = (string) ($value ?? '');

        return $text !== '' && in_array($text[0], ['=', '+', '-', '@', "\t", "\r"], true) && ! is_numeric($text)
            ? "'".$text
            : $text;
    }
}
