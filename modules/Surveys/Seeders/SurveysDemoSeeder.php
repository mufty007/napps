<?php

namespace Modules\Surveys\Seeders;

use App\Enums\ScopeLevel;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\Surveys\Models\Survey;
use Modules\Surveys\Models\SurveyResponse;

/**
 * The NAPPS national needs assessment (open, with a few sample responses) and
 * a Kano chapter pulse check left in draft.
 */
class SurveysDemoSeeder extends Seeder
{
    public function run(): void
    {
        $national = User::query()->where('phone', '+2348000000001')->first() ?? User::query()->first();

        if (! $national) {
            return;
        }

        $needs = Survey::query()->firstOrCreate(
            ['title' => 'NAPPS national needs assessment'],
            [
                'description' => 'Help the association plan its support for the year. Answer once for your school; it takes about five minutes.',
                'status' => Survey::OPEN,
                'created_by' => $national->id,
                'scope_level' => ScopeLevel::National->value,
                'scope_id' => null,
                'opened_at' => now()->subDays(4),
                'closes_at' => now()->addWeeks(3),
            ],
        );

        if (! $needs->questions()->exists()) {
            foreach (self::needsAssessmentQuestions() as $i => $question) {
                $needs->questions()->create([...$question, 'sort' => $i + 1]);
            }
        }

        $this->sampleResponses($needs);

        $kano = User::query()->where('phone', '+2348000000003')->first();
        $chapterId = $kano?->roleAssignments()->where('scope_type', ScopeLevel::Chapter->value)->value('scope_id');

        if ($kano && $chapterId) {
            $pulse = Survey::query()->firstOrCreate(
                ['title' => 'Kano Chapter: term-start pulse check'],
                [
                    'description' => 'Two quick questions before the chapter meeting.',
                    'status' => Survey::DRAFT,
                    'created_by' => $kano->id,
                    'scope_level' => ScopeLevel::Chapter->value,
                    'scope_id' => $chapterId,
                ],
            );

            if (! $pulse->questions()->exists()) {
                $pulse->questions()->create([
                    'type' => 'scale', 'prompt' => 'How ready is your school for the new term?',
                    'options' => ['min' => 1, 'max' => 5, 'min_label' => 'Not ready', 'max_label' => 'Fully ready'],
                    'required' => true, 'sort' => 1,
                ]);
                $pulse->questions()->create([
                    'type' => 'text', 'prompt' => 'What should the chapter discuss at the next meeting?',
                    'options' => null, 'required' => false, 'sort' => 2,
                ]);
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function needsAssessmentQuestions(): array
    {
        return [
            [
                'type' => 'number',
                'prompt' => 'How many pupils are enrolled in your school this term?',
                'options' => null,
                'required' => true,
            ],
            [
                'type' => 'number',
                'prompt' => 'How many teaching staff do you employ?',
                'options' => null,
                'required' => true,
            ],
            [
                'type' => 'mc',
                'prompt' => 'What is your biggest challenge in collecting school fees?',
                'options' => ['choices' => [
                    'Parents pay late',
                    'Parents pay in part only',
                    'Tracking who has paid',
                    'Parents transfer pupils with unpaid fees',
                    'We do not have a major challenge',
                ]],
                'required' => true,
            ],
            [
                'type' => 'scale',
                'prompt' => 'How difficult is it to keep up with government and ministry requirements?',
                'options' => ['min' => 1, 'max' => 5, 'min_label' => 'Very easy', 'max_label' => 'Very difficult'],
                'required' => true,
            ],
            [
                'type' => 'mc',
                'prompt' => 'Which support would help your school most this year?',
                'options' => ['choices' => [
                    'Debt recovery and legal help',
                    'Teacher training and certification',
                    'Bursar and records training',
                    'Group buying for books and supplies',
                    'Lobbying on taxes and levies',
                ]],
                'required' => true,
            ],
            [
                'type' => 'mc',
                'prompt' => 'Do you rent or own your school premises?',
                'options' => ['choices' => ['We own it', 'We rent it', 'Part owned, part rented']],
                'required' => true,
            ],
            [
                'type' => 'scale',
                'prompt' => 'How confident are you that your school can pay its NAPPS dues on time?',
                'options' => ['min' => 1, 'max' => 5, 'min_label' => 'Not confident', 'max_label' => 'Very confident'],
                'required' => false,
            ],
            [
                'type' => 'text',
                'prompt' => 'What one thing should NAPPS do differently for member schools?',
                'options' => null,
                'required' => false,
            ],
        ];
    }

    private function sampleResponses(Survey $survey): void
    {
        $answers = [
            // [school name, [answer per question position]]
            'Al-Ihsan Model Academy' => [640, 28, 'Parents pay late', 3, 'Debt recovery and legal help', 'We rent it', 4, 'Help us follow up on defaulting parents faster.'],
            'Daura Model International School' => [520, 22, 'Tracking who has paid', 4, 'Bursar and records training', 'We own it', 3, ''],
            'Kano Capital International School' => [910, 41, 'Parents pay in part only', 2, 'Teacher training and certification', 'We own it', 5, 'Run more training days outside Kano city.'],
        ];

        $questions = $survey->questions()->get()->values();

        foreach ($answers as $schoolName => $values) {
            $school = School::withoutGlobalScopes()->where('name', $schoolName)->first();

            if (! $school) {
                continue;
            }

            $user = User::query()->whereHas('roleAssignments', fn ($q) => $q
                ->where('scope_type', ScopeLevel::School->value)->where('scope_id', $school->id))->first();

            if (! $user || SurveyResponse::withoutGlobalScopes()->where('survey_id', $survey->id)->where('school_id', $school->id)->exists()) {
                continue;
            }

            $response = SurveyResponse::query()->create([
                'survey_id' => $survey->id,
                'user_id' => $user->id,
                'school_id' => $school->id,
                'submitted_at' => now()->subDays(random_int(1, 3)),
            ]);

            foreach ($questions as $i => $question) {
                $value = $values[$i] ?? null;

                if ($value === null || $value === '') {
                    continue;
                }

                $response->answers()->create(['question_id' => $question->id, 'value' => (string) $value]);
            }
        }
    }
}
