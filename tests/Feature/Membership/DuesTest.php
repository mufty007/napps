<?php

use App\Contracts\PaymentGateway;
use App\Enums\GatewayStatus;
use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Enums\ScopeLevel;
use App\Models\GatewayTransaction;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Services\Payments\PaymentService;
use Modules\Membership\Enums\DueStatus;
use Modules\Membership\Enums\PaymentMethod;
use Modules\Membership\Models\DuesAllocation;
use Modules\Membership\Models\DuesCharge;
use Modules\Membership\Models\DuesPayment;
use Modules\Membership\Models\MembershipDue;
use Modules\Membership\Services\ComplianceService;
use Modules\Membership\Services\DuesLedger;

beforeEach(function () {
    $this->seedRoles();
    $this->org = membershipOrg();
    $this->proprietor = User::factory()->create(['phone' => '+2348035550001']);
    $this->school = memberSchool($this->org['kanoChapter'], ['name' => 'Al-Ihsan Model Academy', 'membership_number' => 'NAPPS-KN-00001'], $this->proprietor);
});

it('lets each level set dues as a draft and issue them to its own member schools', function () {
    $pending = memberSchool($this->org['kanoChapter'], ['membership_status' => MembershipStatus::Pending]);
    $elsewhere = memberSchool($this->org['katsinaNorth']);
    $stateExec = officer(Role::StateExecutive, $this->org['kano']->id);

    $this->actingAs($stateExec)->post(route('membership.charges.store'), [
        'title' => 'Kano State development levy',
        'kind' => 'levy',
        'year' => now()->year,
        'amount' => '5,000',
        'due_date' => now()->addMonth()->toDateString(),
        'share_state' => 70,
        'share_chapter' => 30,
        'share_national' => 50, // ignored: a state cannot give national a share
    ])->assertSessionHasNoErrors();

    $charge = DuesCharge::query()->sole();
    expect($charge->scope_type)->toBe(ScopeLevel::State)
        ->and($charge->scope_id)->toBe($this->org['kano']->id)
        ->and($charge->amount_kobo)->toBe(500_000)
        ->and($charge->shares())->toBe(['state' => 70, 'chapter' => 30])
        ->and($charge->isIssued())->toBeFalse()
        ->and(MembershipDue::withoutGlobalScopes()->count())->toBe(0);

    $this->post(route('membership.charges.issue', $charge))->assertRedirect(route('membership.charges.show', $charge));

    // Only approved members in Kano are billed; their proprietors get an SMS with a pay link.
    expect(MembershipDue::withoutGlobalScopes()->pluck('school_id')->all())->toBe([$this->school->id])
        ->and(smsTo($this->proprietor)[0])->toContain('Kano State development levy of ₦5,000 for Al-Ihsan Model Academy')
        ->and(smsTo($this->proprietor)[0])->toContain('/membership/dues?school='.$this->school->id);

    // Issued charges are locked.
    $this->get(route('membership.charges.edit', $charge))->assertForbidden();
    $this->post(route('membership.charges.issue', $charge))->assertForbidden();
    expect($pending->id)->not->toBe($elsewhere->id);
});

it('checks the split adds up to 100%', function () {
    $this->actingAs(officer(Role::NationalExecutive))->post(route('membership.charges.store'), [
        'title' => 'Annual dues', 'kind' => 'annual', 'year' => now()->year, 'amount' => '25000',
        'due_date' => now()->addMonth()->toDateString(),
        'share_national' => 50, 'share_state' => 30, 'share_chapter' => 10,
    ])->assertSessionHasErrors('share_national');
});

it('shows each level the charges that bill its schools but lets it change only its own', function () {
    $national = issuedCharge(['title' => 'National dues', 'issued_at' => null]);
    $katsinaLevy = issuedCharge(['title' => 'Katsina North levy', 'scope_type' => ScopeLevel::Chapter, 'scope_id' => $this->org['katsinaNorth']->id, 'issued_at' => null]);
    $kanoChapterExec = officer(Role::ChapterExecutive, $this->org['kanoChapter']->id);

    $this->actingAs($kanoChapterExec)->get(route('membership.charges.index'))
        ->assertOk()->assertSee('National dues')->assertDontSee('Katsina North levy');
    $this->get(route('membership.charges.show', $katsinaLevy))->assertForbidden();
    $this->get(route('membership.charges.edit', $national))->assertForbidden();

    $this->actingAs(officer(Role::Secretariat))->get(route('membership.charges.edit', $katsinaLevy))->assertOk();
    $this->actingAs($this->proprietor)->get(route('membership.charges.index'))->assertForbidden();
});

it('takes dues online and makes the school Active with a receipt and split (MEM-3)', function () {
    issuedCharge(['share_national' => 40, 'share_state' => 30, 'share_chapter' => 30, 'amount_kobo' => 2_500_001]);
    issuedCharge(['title' => 'Chapter levy', 'scope_type' => ScopeLevel::Chapter, 'scope_id' => $this->org['kanoChapter']->id, 'amount_kobo' => 300_000, 'share_national' => 0, 'share_chapter' => 100]);
    app(ComplianceService::class)->refresh();
    expect($this->school->fresh()->membership_status)->toBe(MembershipStatus::Owing);

    $this->actingAs($this->proprietor);
    $this->get(route('membership.dues.index'))->assertOk()->assertSee('₦28,000.01');

    $response = $this->post(route('membership.dues.pay'));
    $transaction = GatewayTransaction::query()->sole();
    $response->assertRedirect($transaction->authorization_url);
    expect($transaction->amount_kobo)->toBe(2_800_001)
        ->and($transaction->school_id)->toBe($this->school->id);

    // Payer completes checkout; the callback confirms straight away.
    app(PaymentGateway::class)->setOutcome($transaction->reference, GatewayStatus::Success);
    $this->get(route('payments.callback', ['reference' => $transaction->reference]))
        ->assertRedirect(route('membership.dues.index'))
        ->assertSessionHas('status');

    $payment = DuesPayment::withoutGlobalScopes()->sole();
    expect($payment->method)->toBe(PaymentMethod::Online)
        ->and($payment->amount_kobo)->toBe(2_800_001)
        ->and($payment->receipt_number)->toBe(sprintf('DUES-%d-%06d', now()->year, $payment->id))
        ->and($payment->paid_by)->toBe($this->proprietor->id)
        ->and(MembershipDue::withoutGlobalScopes()->unpaid()->count())->toBe(0)
        ->and($this->school->fresh()->membership_status)->toBe(MembershipStatus::Active);

    // Split ledger: 40/30/30 of the national dues (odd kobo to national) + 100% chapter levy.
    $allocations = DuesAllocation::query()->get()->groupBy(fn ($a) => $a->level->value)->map->sum('amount_kobo');
    expect($allocations->all())->toBe(['national' => 1_000_001, 'state' => 750_000, 'chapter' => 1_050_000])
        ->and(DuesAllocation::query()->where('level', 'chapter')->value('chapter_id'))->toBe($this->org['kanoChapter']->id);

    // Receipt by SMS with a working link.
    $sms = collect(smsTo($this->proprietor))->last();
    expect($sms)->toContain($payment->receipt_number);
    preg_match('/(https?:\/\/\S+)/', $sms, $m);
    $this->get($m[1])->assertOk()->assertHeader('content-type', 'application/pdf');

    $this->get(route('membership.dues.index'))->assertSee('You are paid up')->assertSee($payment->receipt_number);
});

it('credits a payment once however many times it is confirmed', function () {
    issuedCharge();
    $this->actingAs($this->proprietor)->post(route('membership.dues.pay'));
    $transaction = GatewayTransaction::query()->sole();
    app(PaymentGateway::class)->setOutcome($transaction->reference, GatewayStatus::Success);

    // Callback, webhook and the sweep all arrive.
    $this->get(route('payments.callback', ['reference' => $transaction->reference]));
    $payload = json_encode(['event' => 'charge.success', 'data' => ['reference' => $transaction->reference]]);
    $this->call('POST', '/webhooks/payments', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_FAKE_SIGNATURE' => app(PaymentGateway::class)->sign($payload),
    ], $payload)->assertOk();
    app(PaymentService::class)->refresh($transaction->fresh());

    expect(DuesPayment::withoutGlobalScopes()->count())->toBe(1);
});

it('reuses an open checkout instead of starting a second one', function () {
    issuedCharge();
    $this->actingAs($this->proprietor);

    $this->post(route('membership.dues.pay'));
    $this->post(route('membership.dues.pay'));

    expect(GatewayTransaction::query()->count())->toBe(1);
});

it('notes an overpayment when dues were settled another way meanwhile', function () {
    issuedCharge();
    $this->actingAs($this->proprietor)->post(route('membership.dues.pay'));
    $transaction = GatewayTransaction::query()->sole();

    // The chapter records a cash payment before the online one confirms.
    $this->actingAs(officer(Role::ChapterExecutive, $this->org['kanoChapter']->id))
        ->post(route('membership.schools.payments.store', $this->school), [
            'dues' => MembershipDue::withoutGlobalScopes()->pluck('id')->all(),
            'method' => 'cash', 'paid_on' => now()->toDateString(),
        ])->assertSessionHasNoErrors();

    app(PaymentGateway::class)->setOutcome($transaction->reference, GatewayStatus::Success);
    app(PaymentService::class)->refresh($transaction);

    $online = DuesPayment::withoutGlobalScopes()->where('method', PaymentMethod::Online)->sole();
    expect($online->notes)->toContain('₦25,000 to refund or credit')
        ->and($online->dues()->withoutGlobalScopes()->count())->toBe(0);
});

it('lets chapter and state executives record offline payments in their area only', function () {
    issuedCharge();
    $due = MembershipDue::withoutGlobalScopes()->sole();
    $chapterExec = officer(Role::ChapterExecutive, $this->org['kanoChapter']->id);

    $this->actingAs($this->proprietor)
        ->post(route('membership.schools.payments.store', $this->school), ['dues' => [$due->id], 'method' => 'cash', 'paid_on' => now()->toDateString()])
        ->assertForbidden();

    $this->actingAs(officer(Role::ChapterExecutive, $this->org['katsinaNorth']->id))
        ->post(route('membership.schools.payments.store', $this->school), ['dues' => [$due->id], 'method' => 'cash', 'paid_on' => now()->toDateString()])
        ->assertNotFound();

    $this->actingAs($chapterExec)
        ->post(route('membership.schools.payments.store', $this->school), ['dues' => [$due->id], 'method' => 'bank_transfer', 'paid_on' => now()->toDateString()])
        ->assertSessionHasErrors('reference');

    $this->actingAs($chapterExec)
        ->post(route('membership.schools.payments.store', $this->school), [
            'dues' => [$due->id], 'method' => 'bank_transfer', 'reference' => 'TLR-0457', 'paid_on' => now()->subDay()->toDateString(),
        ])
        ->assertSessionHas('status');

    $payment = DuesPayment::withoutGlobalScopes()->sole();
    expect($payment->recorded_by)->toBe($chapterExec->id)
        ->and($payment->amount_kobo)->toBe(2_500_000)
        ->and($payment->reference)->toBe('TLR-0457')
        ->and($due->fresh()->status)->toBe(DueStatus::Paid)
        ->and($this->school->fresh()->membership_status)->toBe(MembershipStatus::Active)
        ->and(collect(smsTo($this->proprietor))->last())->toContain($payment->receipt_number);

    $this->assertDatabaseHas('activity_log', ['log_name' => 'dues', 'subject_type' => DuesPayment::class, 'subject_id' => $payment->id, 'causer_id' => $chapterExec->id]);
});

it('withdraws a charge: unpaid bills are voided, payments stay', function () {
    $charge = issuedCharge();
    $paidSchool = memberSchool($this->org['kanoChapter']);
    app(DuesLedger::class)->bill($charge);
    $paidDue = MembershipDue::withoutGlobalScopes()->where('school_id', $paidSchool->id)->sole();
    app(DuesLedger::class)->recordPayment($paidSchool, collect([$paidDue]), PaymentMethod::Cash, 2_500_000, now());
    app(ComplianceService::class)->refresh();
    expect($this->school->fresh()->membership_status)->toBe(MembershipStatus::Owing);

    $this->actingAs(officer(Role::NationalExecutive))->post(route('membership.charges.cancel', $charge))->assertRedirect();

    expect(MembershipDue::withoutGlobalScopes()->where('school_id', $this->school->id)->sole()->status)->toBe(DueStatus::Void)
        ->and($paidDue->fresh()->status)->toBe(DueStatus::Paid)
        ->and($this->school->fresh()->membership_status)->toBe(MembershipStatus::Active);
});

it('opens the dues page for the school named in an SMS link', function () {
    $second = memberSchool($this->org['kanoChapter'], ['name' => 'Kano Capital International School'], $this->proprietor);

    $this->actingAs($this->proprietor)
        ->get(route('membership.dues.index', ['school' => $second->id]))
        ->assertRedirect(route('membership.dues.index'));

    $this->get(route('membership.dues.index'))->assertSee('Kano Capital International School');
});

it('renders the dues settings screens for a draft and an issued charge', function () {
    $this->actingAs(officer(Role::NationalExecutive));

    $this->get(route('membership.charges.create'))->assertOk()->assertSee('Split');
    $this->post(route('membership.charges.store'), [
        'title' => 'Annual dues 2027', 'kind' => 'annual', 'year' => now()->year + 1, 'amount' => '27,500',
        'due_date' => now()->addMonths(4)->toDateString(), 'share_national' => 40, 'share_state' => 30, 'share_chapter' => 30,
    ])->assertSessionHasNoErrors();
    $draft = DuesCharge::query()->sole();

    $this->get(route('membership.charges.show', $draft))->assertOk()->assertSee('Issue to schools')->assertSee('National 40%');
    $this->get(route('membership.charges.edit', $draft))->assertOk()->assertSee('27500');
    $this->put(route('membership.charges.update', $draft), [
        'title' => 'Annual dues 2027', 'kind' => 'annual', 'year' => now()->year + 1, 'amount' => '30000',
        'due_date' => now()->addMonths(4)->toDateString(), 'share_national' => 100, 'share_state' => 0, 'share_chapter' => 0,
    ])->assertRedirect(route('membership.charges.show', $draft));
    expect($draft->fresh()->amount_kobo)->toBe(3_000_000);

    $this->post(route('membership.charges.issue', $draft));
    $this->get(route('membership.charges.show', $draft))->assertOk()->assertSee('Al-Ihsan Model Academy')->assertSee('Withdraw this charge');
    $this->get(route('membership.charges.index', ['year' => now()->year + 1]))->assertOk()->assertSee('0 of 1 paid');
});

it('switches a multi-role proprietor to the right school from the SMS link', function () {
    // Chapter executive outranks proprietor, so it is the default active role.
    RoleAssignment::grant($this->proprietor, Role::ChapterExecutive, ScopeLevel::Chapter, $this->org['kanoChapter']->id);
    $this->proprietor->forceFill(['two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'), 'two_factor_confirmed_at' => now()])->save();
    issuedCharge();

    $this->actingAs($this->proprietor)->get(route('membership.dues.index'))->assertForbidden();

    $this->get(route('membership.dues.index', ['school' => $this->school->id]))->assertRedirect(route('membership.dues.index'));
    $this->get(route('membership.dues.index'))->assertOk()->assertSee('Pay ₦25,000 online');
});
