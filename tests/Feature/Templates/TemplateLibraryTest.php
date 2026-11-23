<?php

use App\Enums\Role;
use App\Models\School;
use App\Models\User;
use Modules\Templates\Models\DocumentTemplate;
use Modules\Templates\Seeders\TemplatesDemoSeeder;
use Modules\Templates\Services\TemplateService;

beforeEach(function () {
    User::factory()->create(['phone' => '+2348000000000']);
    $this->seed(TemplatesDemoSeeder::class);
});

it('seeds the five starter templates as published and reviewed', function () {
    expect(DocumentTemplate::query()->published()->reviewed()->pluck('slug')->sort()->values()->all())
        ->toBe(['demand_letter', 'exit_form', 'fee_agreement', 'model_lease', 'transfer_clearance']);
});

it('lists published templates and hides drafts from schools', function () {
    DocumentTemplate::query()->create([
        'slug' => 'secret_draft',
        'name' => 'Unfinished Draft Template',
        'category' => 'general',
        'body_html' => '<p>Draft</p>',
        'created_by' => User::query()->value('id'),
    ]);

    $school = School::factory()->create();
    $this->actingAsRole(Role::Proprietor, $school->id);

    $this->get(route('templates.index'))
        ->assertOk()
        ->assertSee('Demand letter for unpaid school fees')
        ->assertSee('School fees agreement')
        ->assertDontSee('Unfinished Draft Template');

    $this->get(route('templates.show', 'secret_draft'))->assertNotFound();
    $this->get(route('templates.fill.create', 'secret_draft'))->assertNotFound();
});

it('filters the library by category', function () {
    $this->actingAsRole(Role::Proprietor, School::factory()->create()->id);

    $this->get(route('templates.index', ['category' => 'leases']))
        ->assertOk()
        ->assertSee('Model school premises lease')
        ->assertDontSee('Demand letter for unpaid school fees');
});

it('previews a template with its fields highlighted', function () {
    $this->actingAsRole(Role::Proprietor, School::factory()->create()->id);

    $this->get(route('templates.show', 'demand_letter'))
        ->assertOk()
        ->assertSee('Legal review')
        ->assertSee('Reviewed')
        ->assertSee('Debtor name')
        ->assertSee('sandbox', false);
});

it('fills the demand letter and downloads a PDF', function () {
    $this->actingAsRole(Role::Proprietor, School::factory()->create()->id);

    $this->get(route('templates.fill.create', 'demand_letter'))
        ->assertOk()
        ->assertSee('Debtor name')
        ->assertSee('name="amount"', false);

    $response = $this->post(route('templates.fill.store', 'demand_letter'), [
        'school_name' => 'Al-Ihsan Model Academy',
        'debtor_name' => 'Mallam Abdullahi Sule',
        'pupil_name' => 'Aisha Sule',
        'amount' => '485,000',
        'due_date' => '2026-12-15',
    ]);

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($response->headers->get('Content-Disposition'))->toContain('attachment')->toContain('.pdf')
        ->and(substr($response->getContent(), 0, 4))->toBe('%PDF');
});

it('substitutes placeholders, formats naira and dates, and escapes input', function () {
    $service = app(TemplateService::class);
    $template = DocumentTemplate::query()->where('slug', 'demand_letter')->firstOrFail();

    $html = $service->renderHtml($template, [
        'debtor_name' => '<b>Mrs. Okonkwo</b>',
        'amount' => '312500',
        'due_date' => '2026-12-15',
    ]);

    expect($html)
        ->toContain('&lt;b&gt;Mrs. Okonkwo&lt;/b&gt;')
        ->toContain('₦312,500')
        ->toContain('15 December 2026')
        ->toContain('class="blank"')
        ->not->toContain('{{debtor_name}}');

    expect($service->render('demand_letter', ['debtor_name' => 'Test']))->toStartWith('%PDF');
});

it('rejects an amount that is not a naira figure', function () {
    $this->actingAsRole(Role::Proprietor, School::factory()->create()->id);

    $this->post(route('templates.fill.store', 'demand_letter'), ['amount' => 'lots'])
        ->assertSessionHasErrors('amount');
});

it('keeps parents and moderators out of the library', function (Role $role) {
    $this->actingAsRole($role);

    $this->get(route('templates.index'))->assertForbidden();
    $this->post(route('templates.fill.store', 'demand_letter'), [])->assertForbidden();
})->with([Role::Parent, Role::Moderator]);

it('stops school roles from managing the library', function () {
    $this->actingAsRole(Role::Proprietor, School::factory()->create()->id);

    $this->get(route('templates.create'))->assertForbidden();
    $this->post(route('templates.publish', 'demand_letter'))->assertForbidden();
});

it('lets the national executive draft, review and publish a template', function () {
    $this->actingAsRole(Role::NationalExecutive);

    $this->post(route('templates.store'), [
        'slug' => 'school_visit_notice',
        'name' => 'School visit notice',
        'category' => 'general',
        'body_html' => '<p>Dear {{parent_name}}, visit on {{visit_date}}.</p>',
    ])->assertRedirect(route('templates.show', 'school_visit_notice'));

    $template = DocumentTemplate::query()->where('slug', 'school_visit_notice')->firstOrFail();
    expect($template->version)->toBe(1)
        ->and(array_keys($template->placeholders))->toBe(['parent_name', 'visit_date'])
        ->and($template->placeholders['visit_date']['type'])->toBe('date');

    // Cannot publish before legal review.
    $this->post(route('templates.publish', $template))->assertSessionHasErrors('publish');
    expect($template->fresh()->isPublished())->toBeFalse();

    $this->post(route('templates.review', $template))->assertSessionHasNoErrors();
    $this->post(route('templates.publish', $template))->assertSessionHasNoErrors();

    $template->refresh();
    expect($template->isReviewed())->toBeTrue()
        ->and($template->reviewed_by)->not->toBeNull()
        ->and($template->isPublished())->toBeTrue();
});

it('bumps the version and withdraws a template when its wording changes', function () {
    $this->actingAsRole(Role::NationalExecutive);
    $template = DocumentTemplate::query()->where('slug', 'exit_form')->firstOrFail();

    // Metadata only: same version, still live.
    $this->put(route('templates.update', $template), [
        'name' => 'Exit form (renamed)',
        'category' => 'records',
        'body_html' => $template->body_html,
    ])->assertRedirect();

    expect($template->fresh())->version->toBe(1)->isPublished()->toBeTrue();

    $this->put(route('templates.update', $template), [
        'name' => 'Exit form (renamed)',
        'category' => 'records',
        'body_html' => '<p>New wording for {{pupil_name}}</p>',
    ])->assertRedirect();

    $template->refresh();
    expect($template->version)->toBe(2)
        ->and($template->isReviewed())->toBeFalse()
        ->and($template->isPublished())->toBeFalse()
        ->and(array_keys($template->placeholders))->toBe(['pupil_name']);
});

it('puts Templates in the navigation for roles that can use them', function () {
    $this->actingAsRole(Role::Proprietor, School::factory()->create()->id);
    $this->get('/dashboard')->assertSee(route('templates.index'));

    $this->actingAsRole(Role::Parent);
    $this->get('/dashboard')->assertDontSee(route('templates.index'));
});
