<?php

use App\Enums\Role;
use App\Enums\ScopeLevel;
use App\Models\RoleAssignment;
use App\Models\School;
use App\Models\User;
use Modules\Surveys\Models\Survey;
use Modules\Surveys\Models\SurveyAnswer;
use Modules\Surveys\Models\SurveyQuestion;
use Modules\Surveys\Models\SurveyResponse;
use Modules\Surveys\Seeders\SurveysDemoSeeder;
use Modules\Surveys\Services\SurveyResults;

beforeEach(function () {
    $this->seedRoles();
    $this->org = membershipOrg();
});

function surveyProprietor(School $school): User
{
    $user = User::factory()->withTwoFactor()->create();
    RoleAssignment::grant($user, Role::Proprietor, ScopeLevel::School, $school->id);

    return $user;
}

/**
 * A survey with one question of each kind (sort order 1..4).
 *
 * @param  array<string, mixed>  $attributes
 */
function surveyWithQuestions(array $attributes = []): Survey
{
    $survey = Survey::query()->create([
        'title' => 'Needs assessment',
        'status' => Survey::OPEN,
        'created_by' => User::factory()->create()->id,
        'scope_level' => ScopeLevel::National->value,
        'scope_id' => null,
        'opened_at' => now(),
        ...$attributes,
    ]);

    $survey->questions()->createMany([
        ['type' => 'mc', 'prompt' => 'Biggest challenge?', 'options' => ['choices' => ['Late fees', 'Staffing']], 'required' => true, 'sort' => 1],
        ['type' => 'scale', 'prompt' => 'Readiness?', 'options' => ['min' => 1, 'max' => 5], 'required' => true, 'sort' => 2],
        ['type' => 'number', 'prompt' => 'Pupils?', 'options' => null, 'required' => true, 'sort' => 3],
        ['type' => 'text', 'prompt' => 'Anything else?', 'options' => null, 'required' => false, 'sort' => 4],
    ]);

    return $survey;
}

/** @return array<string, mixed> */
function surveyAnswers(Survey $survey, string $choice, int $scale, int $pupils, string $text = ''): array
{
    $ids = $survey->questions()->pluck('id')->all();

    return ['answers' => [$ids[0] => $choice, $ids[1] => $scale, $ids[2] => $pupils, $ids[3] => $text]];
}

it('lets an executive build, publish and close a survey', function () {
    $this->actingAsRole(Role::NationalExecutive);

    $this->get(route('surveys.create'))->assertOk()->assertSee('All member schools');

    $this->post(route('surveys.store'), [
        'title' => 'Term needs survey',
        'description' => 'Tell us what you need.',
        'audience' => 'national',
    ])->assertRedirect();

    $survey = Survey::query()->firstOrFail();
    expect($survey->isDraft())->toBeTrue()->and($survey->scope_level)->toBe(ScopeLevel::National);

    // Publishing an empty survey is refused.
    $this->post(route('surveys.publish', $survey))->assertSessionHasErrors('publish');

    $this->post(route('surveys.questions.store', $survey), [
        'type' => 'mc', 'prompt' => 'Biggest challenge?', 'required' => '1', 'choices' => "Late fees\nStaffing\n\nLate fees",
    ])->assertRedirect();
    $this->post(route('surveys.questions.store', $survey), [
        'type' => 'scale', 'prompt' => 'How ready are you?', 'required' => '1', 'scale_min' => 1, 'scale_max' => 5,
        'min_label' => 'Not ready', 'max_label' => 'Ready',
    ])->assertRedirect();
    $this->post(route('surveys.questions.store', $survey), ['type' => 'number', 'prompt' => 'Pupils?', 'required' => '0'])->assertRedirect();
    $this->post(route('surveys.questions.store', $survey), ['type' => 'text', 'prompt' => 'Comments?'])->assertRedirect();

    $questions = $survey->questions()->get();
    expect($questions->pluck('type')->all())->toBe(['mc', 'scale', 'number', 'text'])
        ->and($questions[0]->choices())->toBe(['Late fees', 'Staffing'])
        ->and($questions[1]->scaleRange())->toBe([1, 5])
        ->and($questions[2]->required)->toBeFalse();

    // Reorder: move the last question to the top.
    $this->post(route('surveys.questions.move', [$survey, $questions[3]]), ['direction' => 'up'])->assertRedirect();
    expect($survey->questions()->pluck('prompt')->all())->toBe(['Biggest challenge?', 'How ready are you?', 'Comments?', 'Pupils?']);

    $this->get(route('surveys.edit', $survey))->assertOk()->assertSee('Biggest challenge?')->assertSee('Publish survey');

    $this->post(route('surveys.publish', $survey))->assertRedirect(route('surveys.edit', $survey));
    expect($survey->fresh()->isOpen())->toBeTrue();

    // Questions are locked once live.
    $this->post(route('surveys.questions.store', $survey), ['type' => 'text', 'prompt' => 'Late addition'])->assertSessionHasErrors('prompt');
    expect($survey->questions()->count())->toBe(4);

    $this->get(route('surveys.index'))->assertOk()->assertSee('Term needs survey');

    $this->post(route('surveys.close', $survey))->assertRedirect();
    expect($survey->fresh()->isClosed())->toBeTrue();
});

it('needs two to twelve choices for a multiple-choice question', function () {
    $this->actingAsRole(Role::NationalExecutive);
    $survey = Survey::query()->create(['title' => 'Draft', 'status' => 'draft', 'created_by' => auth()->id(), 'scope_level' => 'national']);

    $this->post(route('surveys.questions.store', $survey), ['type' => 'mc', 'prompt' => 'Pick', 'choices' => 'Only one'])
        ->assertSessionHasErrors('choices');
    expect($survey->questions()->count())->toBe(0);
});

it('restricts who a chapter executive may target', function () {
    $this->actingAsRole(Role::ChapterExecutive, $this->org['kanoChapter']->id);

    $this->post(route('surveys.store'), ['title' => 'Everyone', 'audience' => 'national'])->assertSessionHasErrors('audience');
    $this->post(route('surveys.store'), ['title' => 'Other chapter', 'audience' => 'chapter:'.$this->org['katsinaNorth']->id])->assertSessionHasErrors('audience');
    $this->post(route('surveys.store'), ['title' => 'My chapter', 'audience' => 'chapter:'.$this->org['kanoChapter']->id])->assertRedirect();

    expect(Survey::query()->where('title', 'My chapter')->first())
        ->scope_level->toBe(ScopeLevel::Chapter)
        ->scope_id->toBe($this->org['kanoChapter']->id);
});

it('stops executives editing surveys outside their own place', function () {
    $national = surveyWithQuestions(['title' => 'National one']);

    $this->actingAsRole(Role::ChapterExecutive, $this->org['kanoChapter']->id);

    // Their slice of a national survey is readable, never editable.
    $this->get(route('surveys.edit', $national))->assertOk();
    $this->put(route('surveys.update', $national), ['title' => 'Hijacked'])->assertForbidden();
    $this->post(route('surveys.close', $national))->assertForbidden();

    // A draft national survey is invisible to them.
    $draft = surveyWithQuestions(['title' => 'Secret draft', 'status' => 'draft']);
    $this->get(route('surveys.edit', $draft))->assertForbidden();
    $this->get(route('surveys.index'))->assertSee('National one')->assertDontSee('Secret draft');
});

it('lets a proprietor answer once on behalf of the school', function () {
    $school = memberSchool($this->org['kanoChapter']);
    $survey = surveyWithQuestions();
    $user = surveyProprietor($school);
    $this->actingAs($user);

    $this->get(route('surveys.respond.index'))->assertOk()->assertSee('Needs assessment');
    $this->get(route('surveys.respond.show', $survey))->assertOk()->assertSee('Biggest challenge?')->assertSee('Readiness?');

    $this->post(route('surveys.respond.store', $survey), surveyAnswers($survey, 'Late fees', 4, 320, 'More training please'))
        ->assertRedirect(route('surveys.respond.index'));

    $response = SurveyResponse::query()->where('survey_id', $survey->id)->sole();
    expect($response->school_id)->toBe($school->id)
        ->and($response->user_id)->toBe($user->id)
        ->and($response->answers()->count())->toBe(4);

    // A second attempt does not create another sheet.
    $this->post(route('surveys.respond.store', $survey), surveyAnswers($survey, 'Staffing', 1, 10))->assertRedirect(route('surveys.respond.index'));
    $this->get(route('surveys.respond.show', $survey))->assertRedirect(route('surveys.respond.index'));
    expect(SurveyResponse::query()->where('survey_id', $survey->id)->count())->toBe(1);
});

it('validates answers against each question', function () {
    $school = memberSchool($this->org['kanoChapter']);
    $survey = surveyWithQuestions();
    $this->actingAs(surveyProprietor($school));
    $ids = $survey->questions()->pluck('id')->all();

    $this->post(route('surveys.respond.store', $survey), ['answers' => [$ids[0] => 'Not a choice', $ids[1] => 9, $ids[2] => 'many']])
        ->assertSessionHasErrors(["answers.{$ids[0]}", "answers.{$ids[1]}", "answers.{$ids[2]}"]);

    // Required questions cannot be skipped; the optional text one can.
    $this->post(route('surveys.respond.store', $survey), ['answers' => [$ids[3] => 'only text']])
        ->assertSessionHasErrors(["answers.{$ids[0]}", "answers.{$ids[1]}", "answers.{$ids[2]}"]);

    expect(SurveyResponse::query()->count())->toBe(0);

    $this->post(route('surveys.respond.store', $survey), ['answers' => [$ids[0] => 'Staffing', $ids[1] => 3, $ids[2] => 0]])->assertSessionHasNoErrors();
    expect(SurveyAnswer::query()->count())->toBe(3); // the blank text answer is not stored
});

it('only offers a survey to schools in its audience', function () {
    $kano = memberSchool($this->org['kanoChapter']);
    $katsina = memberSchool($this->org['katsinaNorth']);
    $survey = surveyWithQuestions([
        'title' => 'Kano only', 'scope_level' => ScopeLevel::Chapter->value, 'scope_id' => $this->org['kanoChapter']->id,
    ]);

    $this->actingAs(surveyProprietor($katsina));
    $this->get(route('surveys.respond.index'))->assertOk()->assertDontSee('Kano only');
    $this->get(route('surveys.respond.show', $survey))->assertNotFound();
    $this->post(route('surveys.respond.store', $survey), surveyAnswers($survey, 'Late fees', 3, 10))->assertNotFound();

    $this->actingAs(surveyProprietor($kano));
    $this->get(route('surveys.respond.index'))->assertSee('Kano only');
    $this->get(route('surveys.respond.show', $survey))->assertOk();
});

it('does not accept answers to drafts or closed surveys', function () {
    $school = memberSchool($this->org['kanoChapter']);
    $this->actingAs(surveyProprietor($school));

    $draft = surveyWithQuestions(['status' => 'draft']);
    $closed = surveyWithQuestions(['status' => 'closed']);
    $expired = surveyWithQuestions(['closes_at' => now()->subHour()]);

    foreach ([$draft, $closed, $expired] as $survey) {
        $this->get(route('surveys.respond.show', $survey))->assertNotFound();
        $this->post(route('surveys.respond.store', $survey), surveyAnswers($survey, 'Late fees', 3, 10))->assertNotFound();
    }

    expect(SurveyResponse::query()->count())->toBe(0);
});

it('keeps surveys away from roles that cannot use them', function () {
    $school = memberSchool($this->org['kanoChapter']);
    $survey = surveyWithQuestions();

    $this->actingAsRole(Role::Bursar, $school->id);
    $this->get(route('surveys.respond.index'))->assertForbidden();
    $this->get(route('surveys.index'))->assertForbidden();

    $this->actingAsRole(Role::Parent);
    $this->get(route('surveys.respond.show', $survey))->assertForbidden();

    // A proprietor can answer but cannot build or read results.
    $this->actingAs(surveyProprietor($school));
    $this->get(route('surveys.index'))->assertForbidden();
    $this->get(route('surveys.create'))->assertForbidden();
    $this->get(route('surveys.results', $survey))->assertForbidden();
});

it('summarises results and response rates by chapter and state', function () {
    $kanoA = memberSchool($this->org['kanoChapter']);
    $kanoB = memberSchool($this->org['kanoChapter']);
    $katsina = memberSchool($this->org['katsinaNorth']);
    memberSchool($this->org['katsinaNorth']); // never answers

    $survey = surveyWithQuestions();

    foreach ([[$kanoA, 'Late fees', 5, 100, 'Great'], [$kanoB, 'Late fees', 3, 200, ''], [$katsina, 'Staffing', 1, 300, 'Help']] as [$school, $choice, $scale, $pupils, $text]) {
        $this->actingAs(surveyProprietor($school))
            ->post(route('surveys.respond.store', $survey), surveyAnswers($survey, $choice, $scale, $pupils, $text));
    }

    $this->actingAsRole(Role::NationalExecutive);

    $page = $this->get(route('surveys.results', $survey))->assertOk();
    $page->assertSee('Kano Chapter')->assertSee('Katsina North Chapter')->assertSee('Great')->assertSee('Help');

    $coverage = app(SurveyResults::class)->coverage($survey);
    expect($coverage['schools'])->toBe(4)->and($coverage['responded'])->toBe(3)
        ->and($coverage['chapters']->firstWhere('chapter', 'Kano Chapter'))->toMatchArray(['schools' => 2, 'responded' => 2, 'rate' => 100])
        ->and($coverage['chapters']->firstWhere('chapter', 'Katsina North Chapter'))->toMatchArray(['schools' => 2, 'responded' => 1, 'rate' => 50]);

    $questions = $survey->questions()->get();
    $summary = app(SurveyResults::class)->summarise($survey, $questions);

    expect($summary[$questions[0]->id]['rows'][0])->toMatchArray(['label' => 'Late fees', 'count' => 2, 'percent' => 67])
        ->and($summary[$questions[1]->id]['average'])->toBe(3.0)
        ->and($summary[$questions[2]->id])->toMatchArray(['sum' => 600.0, 'min' => 100.0, 'max' => 300.0, 'average' => 200.0])
        ->and($summary[$questions[3]->id]['count'])->toBe(2);

    // Filtering by chapter narrows both the cards and the numbers.
    $filtered = app(SurveyResults::class)->summarise($survey, $questions, null, $this->org['katsinaNorth']->id);
    expect($filtered[$questions[2]->id]['sum'])->toBe(300.0);
    $this->get(route('surveys.results', ['survey' => $survey, 'chapter_id' => $this->org['katsinaNorth']->id]))
        ->assertOk()->assertSee('Help')->assertDontSee('Great');
});

it('shows an executive only the responses from their own chapter', function () {
    $kano = memberSchool($this->org['kanoChapter']);
    $katsina = memberSchool($this->org['katsinaNorth']);
    $survey = surveyWithQuestions();

    $this->actingAs(surveyProprietor($kano))->post(route('surveys.respond.store', $survey), surveyAnswers($survey, 'Late fees', 3, 10, 'Kano comment'));
    $this->actingAs(surveyProprietor($katsina))->post(route('surveys.respond.store', $survey), surveyAnswers($survey, 'Late fees', 3, 10, 'Katsina comment'));

    $this->actingAsRole(Role::ChapterExecutive, $this->org['kanoChapter']->id);

    $this->get(route('surveys.results', $survey))
        ->assertOk()->assertSee('Kano comment')->assertDontSee('Katsina comment');

    $csv = $this->get(route('surveys.results.export', $survey))->streamedContent();
    expect($csv)->toContain('Kano comment')->not->toContain('Katsina comment');
});

it('exports results to CSV with one row per school', function () {
    $school = memberSchool($this->org['kanoChapter'], ['name' => 'Al-Ihsan Model Academy']);
    $survey = surveyWithQuestions();

    $this->actingAs(surveyProprietor($school))->post(
        route('surveys.respond.store', $survey),
        surveyAnswers($survey, 'Staffing', 2, 450, '=HYPERLINK("http://evil.example")'),
    );

    $this->actingAsRole(Role::NationalExecutive);

    $response = $this->get(route('surveys.results.export', $survey))->assertOk();
    expect($response->headers->get('Content-Disposition'))->toContain('survey-'.$survey->id.'-results.csv');

    $lines = array_values(array_filter(preg_split('/\R/', ltrim($response->streamedContent(), "\xEF\xBB\xBF"))));
    expect($lines)->toHaveCount(2)
        ->and($lines[0])->toContain('School')->toContain('Biggest challenge?')->toContain('Pupils?')
        ->and($lines[1])->toContain('Al-Ihsan Model Academy')->toContain('Kano Chapter')->toContain('Kano')->toContain('Staffing')->toContain('450')
        // Spreadsheet formulas are defused.
        ->and($lines[1])->toContain("\"'=HYPERLINK");
});

it('refuses to delete a live survey that already has responses', function () {
    $school = memberSchool($this->org['kanoChapter']);
    $survey = surveyWithQuestions();
    $this->actingAs(surveyProprietor($school))->post(route('surveys.respond.store', $survey), surveyAnswers($survey, 'Late fees', 3, 10));

    $this->actingAsRole(Role::NationalExecutive);
    $this->delete(route('surveys.destroy', $survey))->assertSessionHasErrors('survey');
    expect(Survey::query()->count())->toBe(1);

    $draft = surveyWithQuestions(['status' => 'draft', 'title' => 'Scrap this']);
    $this->delete(route('surveys.destroy', $draft))->assertRedirect(route('surveys.index'));
    expect(Survey::query()->where('title', 'Scrap this')->exists())->toBeFalse();
});

it('puts Surveys in the navigation for executives and proprietors only', function () {
    $school = memberSchool($this->org['kanoChapter']);

    $this->actingAsRole(Role::NationalExecutive);
    $this->get('/dashboard')->assertSee(route('surveys.index'));

    $this->actingAs(surveyProprietor($school));
    $this->get('/dashboard')->assertSee(route('surveys.respond.index'))->assertDontSee(route('surveys.index'));

    $this->actingAsRole(Role::Parent);
    $this->get('/dashboard')->assertDontSee(route('surveys.respond.index'));
});

it('seeds the NAPPS needs assessment', function () {
    User::factory()->create(['phone' => '+2348000000001']);

    $this->seed(SurveysDemoSeeder::class);
    $this->seed(SurveysDemoSeeder::class); // idempotent

    $survey = Survey::query()->where('title', 'NAPPS national needs assessment')->sole();
    expect($survey->isOpen())->toBeTrue()
        ->and($survey->scope_level)->toBe(ScopeLevel::National)
        ->and($survey->questions()->count())->toBe(count(SurveysDemoSeeder::needsAssessmentQuestions()))
        ->and($survey->questions()->pluck('type')->unique()->sort()->values()->all())->toBe(['mc', 'number', 'scale', 'text'])
        ->and(SurveyQuestion::query()->count())->toBe(count(SurveysDemoSeeder::needsAssessmentQuestions()));
});
