<?php

use App\Enums\Role;
use App\Enums\ScopeLevel;
use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Modules\Claims\Enums\ClaimEventType;
use Modules\Claims\Enums\ClaimStage;
use Modules\Claims\Exceptions\ClaimsNotEnabled;
use Modules\Claims\Models\ClaimCase;
use Modules\Claims\Models\StateClaimSetting;
use Modules\Claims\Services\ClaimService;
use Modules\Templates\Seeders\TemplatesDemoSeeder;
use Modules\Templates\Services\TemplateService;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Storage::fake('local');
    $this->seedRoles();

    $this->org = membershipOrg();
    $this->proprietor = User::factory()->create(['phone' => '+2348031110001']);
    $this->school = memberSchool($this->org['kanoChapter'], ['name' => 'Al-Ihsan Model Academy'], $this->proprietor);

    StateClaimSetting::query()->create([
        'state_id' => $this->org['kano']->id,
        'enabled' => true,
        'barrister_confirmed_at' => now(),
    ]);

    $this->lawyer = User::factory()->create(['phone' => '+2348031110002']);
    RoleAssignment::grant($this->lawyer, Role::LegalPanel, ScopeLevel::Personal);
    $this->secretariat = officer(Role::Secretariat);
});

function claimPayload(array $overrides = []): array
{
    return [
        'debtor_name' => 'Mrs. Grace Okonkwo',
        'debtor_phone' => '0802 411 9002',
        'amount' => '312,500',
        'days_overdue' => 102,
        'summary' => 'Second child balance unpaid.',
        ...$overrides,
    ];
}

function openClaim(array $attributes = []): ClaimCase
{
    $case = app(ClaimService::class)->openCase(test()->proprietor, test()->school, [
        'debtor_name' => 'Mallam Abdullahi Sule',
        'debtor_phone' => '08032001001',
        'amount_kobo' => 485_000_00,
        'summary' => 'Term fees unpaid.',
    ]);

    return $attributes === [] ? $case : tap($case)->update($attributes);
}

it('lets a proprietor open a claim and starts the timeline', function () {
    $this->actingAs($this->proprietor)->get(route('claims.create'))->assertOk()->assertSee('Al-Ihsan Model Academy');

    $response = $this->actingAs($this->proprietor)->post(route('claims.store'), claimPayload());

    $case = ClaimCase::query()->firstOrFail();
    $response->assertRedirect(route('claims.show', $case));

    expect($case->reference)->toBe('CLM-KN-'.now()->year.'-001')
        ->and($case->amount_kobo)->toBe(312_500_00)
        ->and($case->stage)->toBe(ClaimStage::DemandSent)
        ->and($case->debtor_phone)->toBe('+2348024119002')
        ->and($case->school_id)->toBe($this->school->id)
        ->and($case->opened_by)->toBe($this->proprietor->id);

    $event = $case->events()->firstOrFail();
    expect($event->type)->toBe(ClaimEventType::StageChange)
        ->and($event->meta['to'])->toBe('demand_sent')
        ->and($event->user_id)->toBe($this->proprietor->id);

    expect(Activity::query()->where('log_name', 'claims')->where('event', 'opened')->count())->toBe(1);

    // References count up per state and year.
    $second = openClaim();
    expect($second->reference)->toBe('CLM-KN-'.now()->year.'-002');

    $this->get(route('claims.show', $case))->assertOk()->assertSee('Mrs. Grace Okonkwo')->assertSee('₦312,500');
});

it('validates the claim form', function () {
    $this->actingAs($this->proprietor)
        ->post(route('claims.store'), claimPayload(['debtor_name' => '', 'amount' => 'lots', 'debtor_phone' => '123']))
        ->assertSessionHasErrors(['debtor_name', 'amount', 'debtor_phone']);

    expect(ClaimCase::query()->count())->toBe(0);
});

it('blocks opening a claim in a state where claims are switched off', function () {
    StateClaimSetting::query()->where('state_id', $this->org['kano']->id)->update(['enabled' => false]);

    $this->actingAs($this->proprietor)->get(route('claims.create'))->assertOk()->assertSee('not switched on');

    $this->post(route('claims.store'), claimPayload())->assertSessionHas('error');
    expect(ClaimCase::query()->count())->toBe(0);

    expect(fn () => app(ClaimService::class)->ensureStateEnabled($this->school))->toThrow(ClaimsNotEnabled::class);

    // A state with no settings row at all is off too.
    StateClaimSetting::query()->delete();
    expect(app(ClaimService::class)->isStateEnabled($this->school->state_id))->toBeFalse();
});

it('writes an event, an audit entry and notices when the stage changes', function () {
    $case = openClaim();
    app(ClaimService::class)->assignLawyer($case, $this->lawyer, $this->secretariat);

    $this->actingAs($this->lawyer)
        ->put(route('claims.update', $case), [
            'stage' => 'filed',
            'stage_note' => 'Filed at the Kano small claims court.',
            'court_name' => 'Kano Magistrate Court',
            'court_file_number' => 'SC/KN/12/2026',
        ])
        ->assertRedirect();

    $case->refresh();
    expect($case->stage)->toBe(ClaimStage::Filed)
        ->and($case->court_name)->toBe('Kano Magistrate Court');

    $stageEvent = $case->events()->where('type', 'stage_change')->latest('id')->first();
    expect($stageEvent->meta)->toMatchArray(['from' => 'demand_sent', 'to' => 'filed'])
        ->and($stageEvent->body)->toBe('Filed at the Kano small claims court.')
        ->and($stageEvent->user_id)->toBe($this->lawyer->id)
        ->and($case->events()->where('type', 'filing')->count())->toBe(1)
        ->and($case->events()->where('type', 'assignment')->count())->toBe(1);

    expect(Activity::query()->where('log_name', 'claims')->where('event', 'stage_changed')->count())->toBe(1);

    // The school that opened the claim hears about it, in-app and by SMS.
    expect($this->proprietor->notifications()->where('data->title', 'like', '%Filed in court%')->count())->toBe(1)
        ->and(smsTo($this->proprietor)[0])->toContain('Filed in court');

    // Saving the same stage again changes nothing.
    $before = $case->events()->count();
    $this->put(route('claims.update', $case), ['stage' => 'filed'])->assertRedirect();
    expect($case->events()->count())->toBe($before);
});

it('stamps closed_at when closing and clears it when reopened', function () {
    $case = openClaim();
    app(ClaimService::class)->assignLawyer($case, $this->lawyer, $this->secretariat);

    $this->actingAs($this->lawyer)->put(route('claims.update', $case), ['stage' => 'closed'])->assertRedirect();
    expect($case->fresh()->closed_at)->not->toBeNull()->and($case->fresh()->isClosed())->toBeTrue();

    $this->put(route('claims.update', $case), ['stage' => 'enforcement'])->assertRedirect();
    expect($case->fresh()->closed_at)->toBeNull();
});

it('answers the ajax update with JSON', function () {
    $case = openClaim();
    app(ClaimService::class)->assignLawyer($case, $this->lawyer, $this->secretariat);

    $this->actingAs($this->lawyer)
        ->putJson(route('claims.update', $case), ['stage' => 'mediation'])
        ->assertOk()
        ->assertJson(['reload' => true, 'stage' => 'mediation'])
        ->assertJsonPath('status', 'Stage is now Chapter mediation.');
});

it('only lets the assigned lawyer or the secretariat work a case', function () {
    $case = openClaim();
    app(ClaimService::class)->assignLawyer($case, $this->lawyer, $this->secretariat);

    $other = User::factory()->create();
    RoleAssignment::grant($other, Role::LegalPanel, ScopeLevel::Personal);

    $this->actingAs($other)->get(route('claims.show', $case))->assertForbidden();
    $this->put(route('claims.update', $case), ['stage' => 'closed'])->assertForbidden();

    // The school can see its claim but not work it.
    $this->actingAs($this->proprietor)->get(route('claims.show', $case))->assertOk();
    $this->put(route('claims.update', $case), ['stage' => 'closed'])->assertForbidden();
    $this->post(route('claims.hearings.store', $case), ['scheduled_at' => now()->addWeek()->toDateTimeString()])->assertForbidden();

    $this->actingAs($this->secretariat)->put(route('claims.update', $case), ['stage' => 'response_window'])->assertRedirect();
    expect($case->fresh()->stage)->toBe(ClaimStage::ResponseWindow);
});

it('keeps claims inside the viewer\'s school, chapter and state', function () {
    $case = openClaim();

    $kanoChapterExec = officer(Role::ChapterExecutive, $this->org['kanoChapter']->id);
    $katsinaExec = officer(Role::StateExecutive, $this->org['katsina']->id);
    $kanoStateExec = officer(Role::StateExecutive, $this->org['kano']->id);

    $this->actingAs($kanoChapterExec)->get(route('claims.show', $case))->assertOk();
    $this->actingAs($kanoStateExec)->get(route('claims.index'))->assertOk()->assertSee($case->reference);
    $this->actingAs($katsinaExec)->get(route('claims.index'))->assertOk()->assertDontSee($case->reference);
    $this->actingAs($katsinaExec)->get(route('claims.show', $case))->assertForbidden();

    $rival = User::factory()->create();
    memberSchool($this->org['kanoChapter'], ['name' => 'Rival Academy'], $rival);
    $this->actingAs($rival)->get(route('claims.show', $case))->assertForbidden();
    $this->actingAs($rival)->get(route('claims.index'))->assertDontSee($case->reference);

    // A lawyer sees only the files assigned to them.
    $this->actingAs($this->lawyer)->get(route('claims.index'))->assertDontSee($case->reference);
});

it('filters the list by status, stage and search, and serves the ajax partial', function () {
    $open = openClaim();
    $court = openClaim(['stage' => ClaimStage::Filed, 'debtor_name' => 'Alhaji Musa Dankano']);
    $closed = openClaim(['stage' => ClaimStage::Closed, 'closed_at' => now(), 'debtor_name' => 'Mr. Chinedu Eze']);

    $this->actingAs($this->secretariat);

    $this->get(route('claims.index'))->assertOk()
        ->assertSee($open->reference)->assertSee($court->reference)->assertDontSee($closed->reference);

    $this->get(route('claims.index', ['status' => 'closed']))->assertOk()
        ->assertSee($closed->reference)->assertDontSee($open->reference);

    $this->get(route('claims.index', ['stage' => 'filed']))->assertOk()
        ->assertSee($court->reference)->assertDontSee($open->reference);

    $this->get(route('claims.index', ['q' => 'Dankano']))->assertOk()
        ->assertSee($court->reference)->assertDontSee($open->reference);

    $this->get(route('claims.index', ['q' => 'Al-Ihsan']))->assertOk()->assertSee($open->reference);

    $partial = $this->get(route('claims.index', ['status' => 'closed']), ['X-Requested-With' => 'XMLHttpRequest']);
    $partial->assertOk()->assertSee($closed->reference)->assertSee('data-ajax-results', false)->assertDontSee('<html', false);
});

it('lets only the secretariat assign panel lawyers', function () {
    $case = openClaim();

    $this->actingAs($this->proprietor)->post(route('claims.assign', $case), ['lawyer_id' => $this->lawyer->id])->assertForbidden();

    $this->actingAs($this->secretariat)->post(route('claims.assign', $case), ['lawyer_id' => $this->lawyer->id])->assertRedirect();
    expect($case->fresh()->lawyer_id)->toBe($this->lawyer->id)
        ->and($this->lawyer->notifications()->count())->toBe(1)
        ->and(smsTo($this->lawyer)[0])->toContain($case->reference);

    // Someone off the panel cannot be assigned.
    $this->post(route('claims.assign', $case), ['lawyer_id' => $this->proprietor->id])->assertSessionHas('error');
    expect($case->fresh()->lawyer_id)->toBe($this->lawyer->id);
});

it('schedules and updates hearings', function () {
    $case = openClaim(['court_name' => 'Kano Magistrate Court']);
    app(ClaimService::class)->assignLawyer($case, $this->lawyer, $this->secretariat);

    $this->actingAs($this->lawyer)
        ->post(route('claims.hearings.store', $case), ['scheduled_at' => now()->addDays(3)->setTime(10, 0)->format('Y-m-d\TH:i')])
        ->assertRedirect();

    $hearing = $case->hearings()->firstOrFail();
    expect($hearing->venue)->toBe('Kano Magistrate Court')
        ->and($case->fresh()->load('hearings')->nextHearing()?->id)->toBe($hearing->id)
        ->and($case->events()->where('type', 'hearing')->count())->toBe(1);

    $this->put(route('claims.hearings.update', [$case, $hearing]), ['outcome' => 'Adjourned for defence.'])->assertRedirect();
    expect($hearing->fresh()->outcome)->toBe('Adjourned for defence.')
        ->and($case->events()->where('type', 'hearing')->count())->toBe(2);

    // A hearing of another case cannot be edited through this one.
    $other = openClaim();
    $this->put(route('claims.hearings.update', [$other, $hearing]), ['outcome' => 'x'])->assertNotFound();
});

it('records settlements and fees in kobo and audits the money', function () {
    $case = openClaim();
    app(ClaimService::class)->assignLawyer($case, $this->lawyer, $this->secretariat);

    $this->actingAs($this->lawyer);
    $this->post(route('claims.settlements.store', $case), [
        'amount' => '200,000.50',
        'terms' => 'Two instalments.',
        'paid_at' => now()->subDay()->toDateString(),
    ])->assertRedirect();
    $this->post(route('claims.settlements.store', $case), ['amount' => '0'])->assertSessionHasErrors('amount');
    $this->post(route('claims.fees.store', $case), ['description' => 'Filing fee', 'amount' => '5000'])->assertRedirect();

    $settlement = $case->settlements()->firstOrFail();
    expect($settlement->amount_kobo)->toBe(200_000_50)
        ->and($settlement->paid_at)->not->toBeNull()
        ->and($case->fees()->firstOrFail()->amount_kobo)->toBe(5_000_00)
        ->and($case->events()->where('type', 'settlement')->count())->toBe(1)
        ->and($case->events()->where('type', 'payment')->count())->toBe(1)
        ->and(Activity::query()->where('log_name', 'claims')->whereIn('event', ['settlement_recorded', 'fee_recorded'])->count())->toBe(2);

    $totals = app(ClaimService::class)->totals($case);
    expect($totals['paid_kobo'])->toBe(200_000_50)->and($totals['outstanding_kobo'])->toBe(485_000_00 - 200_000_50);

    $this->get(route('claims.show', $case))->assertOk()->assertSee('₦200,000.50')->assertSee('Filing fee');
});

it('builds the claim pack checklist and takes uploads', function () {
    $case = openClaim();
    $service = app(ClaimService::class);

    $statuses = fn () => collect($service->buildPackChecklist($case->fresh()))->pluck('status', 'key')->all();
    expect($statuses())->toBe(['demand_letter' => 'missing', 'evidence' => 'missing', 'pleadings' => 'optional', 'judgment' => 'optional']);

    // The owning school adds evidence...
    $this->actingAs($this->proprietor)
        ->post(route('claims.documents.store', $case), ['collection' => 'evidence', 'title' => 'Fee agreement', 'file' => fakePdf('agreement.pdf')])
        ->assertRedirect();

    // ...but not court papers.
    $this->post(route('claims.documents.store', $case), ['collection' => 'pleadings', 'file' => fakePdf('claim.pdf')])
        ->assertSessionHasErrors('collection');

    expect($statuses()['evidence'])->toBe('ready')
        ->and($case->fresh()->getFirstMedia('evidence')->name)->toBe('Fee agreement')
        ->and($case->events()->where('type', 'document')->count())->toBe(1);

    $media = $case->fresh()->getFirstMedia('evidence');
    $this->get(route('claims.documents.show', [$case, $media]))->assertOk();

    // Pleadings become required once the claim is filed; the lawyer can add them.
    app(ClaimService::class)->assignLawyer($case, $this->lawyer, $this->secretariat);
    app(ClaimService::class)->transitionStage($case, ClaimStage::Filed, $this->lawyer);
    expect($statuses()['pleadings'])->toBe('missing');

    $this->actingAs($this->lawyer)
        ->post(route('claims.documents.store', $case), ['collection' => 'pleadings', 'file' => fakePdf('claim.pdf')])
        ->assertRedirect();
    expect($statuses()['pleadings'])->toBe('ready');

    // A file from another case cannot be opened through this one.
    $other = openClaim();
    $this->actingAs($this->secretariat)->get(route('claims.documents.show', [$other, $media]))->assertNotFound();
});

it('hides internal notes from the school', function () {
    $case = openClaim();
    app(ClaimService::class)->assignLawyer($case, $this->lawyer, $this->secretariat);

    $this->actingAs($this->lawyer)->post(route('claims.notes.store', $case), ['body' => 'Debtor offered half upfront.'])->assertRedirect();

    $this->get(route('claims.show', $case))->assertSee('Debtor offered half upfront.');
    $this->actingAs($this->proprietor)->get(route('claims.show', $case))->assertOk()->assertDontSee('Debtor offered half upfront.');
});

it('fills the demand letter template into the claim pack', function () {
    $this->seed(TemplatesDemoSeeder::class);
    $case = openClaim();

    $this->actingAs($this->proprietor)->post(route('claims.demand-letter', $case))->assertRedirect();

    $media = $case->fresh()->getFirstMedia('demand_letter');
    expect($media)->not->toBeNull()
        ->and($media->mime_type)->toBe('application/pdf')
        ->and($case->events()->where('type', 'document')->count())->toBe(1);

    // Generating again replaces the single demand letter.
    $this->post(route('claims.demand-letter', $case))->assertRedirect();
    expect($case->fresh()->getMedia('demand_letter'))->toHaveCount(1);
})->skip(fn () => ! class_exists(TemplateService::class), 'Templates module not installed');

it('lets the secretariat switch claims on for a state, but only with barrister confirmation', function () {
    $this->actingAs($this->proprietor)->get(route('claims.settings.states'))->assertForbidden();
    $this->put(route('claims.settings.states.update', $this->org['katsina']), ['enabled' => '1'])->assertForbidden();

    $this->actingAs($this->secretariat)->get(route('claims.settings.states'))->assertOk()->assertSee('Katsina')->assertSee('Kano');

    $this->put(route('claims.settings.states.update', $this->org['katsina']), ['enabled' => '1', 'barrister_confirmed' => '0'])
        ->assertSessionHas('error');
    expect(StateClaimSetting::query()->where('state_id', $this->org['katsina']->id)->exists())->toBeFalse();

    $this->put(route('claims.settings.states.update', $this->org['katsina']), [
        'enabled' => '1',
        'barrister_confirmed' => '1',
        'notes' => 'Barr. Bello, Bello & Co.',
    ])->assertRedirect();

    $setting = StateClaimSetting::query()->where('state_id', $this->org['katsina']->id)->firstOrFail();
    expect($setting->enabled)->toBeTrue()
        ->and($setting->barrister_confirmed_at)->not->toBeNull()
        ->and($setting->updated_by)->toBe($this->secretariat->id);

    $this->put(route('claims.settings.states.update', $this->org['katsina']), ['enabled' => '0'])->assertRedirect();
    expect($setting->fresh()->enabled)->toBeFalse()->and($setting->fresh()->barrister_confirmed_at)->not->toBeNull();
});

it('shows a lawyer their open cases and hearings this week on the dashboard', function () {
    $case = openClaim(['court_name' => 'Kano Magistrate Court']);
    app(ClaimService::class)->assignLawyer($case, $this->lawyer, $this->secretariat);
    $at = now()->startOfWeek()->addDays(2)->setTime(10, 0);
    app(ClaimService::class)->scheduleHearing($case, $this->lawyer, $at);

    $this->actingAs($this->lawyer)->get('/dashboard')
        ->assertOk()
        ->assertSee('Open cases')
        ->assertSee('Hearings this week')
        ->assertSee($case->reference)
        ->assertSee($at->format('D j M, g:ia'));
});
