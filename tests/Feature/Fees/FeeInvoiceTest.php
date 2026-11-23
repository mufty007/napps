<?php

use App\Contracts\PaymentGateway;
use App\Enums\GatewayStatus;
use App\Enums\Role;
use App\Enums\ScopeLevel;
use App\Events\PaymentConfirmed;
use App\Models\GatewayTransaction;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Services\Payments\PaymentService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Claims\Models\ClaimCase;
use Modules\Claims\Models\StateClaimSetting;
use Modules\Fees\Enums\FeePaymentMethod;
use Modules\Fees\Enums\InvoiceStatus;
use Modules\Fees\Enums\ReminderKind;
use Modules\Fees\Models\FeeAgreement;
use Modules\Fees\Models\FeeInvoice;
use Modules\Fees\Models\FeePayment;
use Modules\Fees\Models\FeeReminder;
use Modules\Fees\Models\FeeStructure;
use Modules\Fees\Services\FeeInvoiceService;

beforeEach(function () {
    Storage::fake('local');
    $this->seedRoles();

    $this->org = membershipOrg();
    $this->proprietor = User::factory()->create(['phone' => '+2348035550001']);
    $this->school = memberSchool($this->org['kanoChapter'], ['name' => 'Al-Ihsan Model Academy'], $this->proprietor);
    $this->bursar = officer(Role::Bursar, $this->school->id, ['phone' => '+2348035550002']);

    $this->parent = User::factory()->create(['phone' => '+2348035550101', 'name' => 'Aisha Garba']);
    RoleAssignment::grant($this->parent, Role::Parent, ScopeLevel::Personal);

    $this->structure = makeFeeStructure($this->school);
});

function makeFeeStructure($school, array $items = [['Tuition', 120_000_00], ['Books', 30_000_00]]): FeeStructure
{
    $structure = FeeStructure::withoutGlobalScopes()->create([
        'school_id' => $school->id,
        'name' => 'Primary 1–3',
        'academic_year' => '2026/2027',
        'term' => 'First term',
        'active' => true,
    ]);

    foreach ($items as $i => [$name, $kobo]) {
        $structure->items()->create(['name' => $name, 'amount_kobo' => $kobo, 'sort' => $i]);
    }

    return $structure->load('items');
}

/**
 * An issued invoice for 150,000 naira, due $dueInDays from today (negative = late).
 */
function makeFeeInvoice(int $dueInDays = 14, array $attributes = []): FeeInvoice
{
    $school = $attributes['school'] ?? test()->school;
    unset($attributes['school']);

    return FeeInvoice::withoutGlobalScopes()->create([
        'school_id' => $school->id,
        'student_name' => 'Amina Garba',
        'guardian_user_id' => test()->parent->id,
        'guardian_name' => 'Aisha Garba',
        'guardian_phone' => test()->parent->phone,
        'fee_structure_id' => test()->structure->id,
        'reference' => 'FEE-T-'.strtoupper(Str::random(8)),
        'lines' => [['name' => 'Tuition', 'amount_kobo' => 120_000_00], ['name' => 'Books', 'amount_kobo' => 30_000_00]],
        'total_kobo' => 150_000_00,
        'paid_kobo' => 0,
        'due_date' => today()->addDays($dueInDays),
        'status' => $dueInDays < 0 ? InvoiceStatus::Overdue : InvoiceStatus::Issued,
        'issued_at' => now()->subDays(30),
        ...$attributes,
    ]);
}

it('lets a proprietor set a fee structure and a bursar only read it', function () {
    $this->actingAs($this->proprietor)->post(route('fees.structures.store'), [
        'name' => 'Nursery',
        'academic_year' => '2026/2027',
        'term' => 'First term',
        'active' => '1',
        'items' => [
            ['name' => 'Tuition', 'amount' => '90,000'],
            ['name' => 'Activities', 'amount' => '10000.50'],
        ],
    ])->assertRedirect(route('fees.structures.index'))->assertSessionHasNoErrors();

    $nursery = FeeStructure::withoutGlobalScopes()->where('name', 'Nursery')->firstOrFail();
    expect($nursery->school_id)->toBe($this->school->id)
        ->and($nursery->totalKobo())->toBe(100_000_50)
        ->and($nursery->items->pluck('amount_kobo')->all())->toBe([90_000_00, 10_000_50]);

    $this->get(route('fees.structures.index'))->assertOk()->assertSee('Nursery')->assertSee('₦100,000.50');

    $this->actingAs($this->bursar)->get(route('fees.structures.index'))->assertOk()->assertSee('Nursery');
    $this->get(route('fees.structures.create'))->assertForbidden();
    $this->get(route('fees.structures.edit', $nursery))->assertForbidden();

    $this->actingAs($this->proprietor)->post(route('fees.structures.store'), [
        'name' => 'Bad', 'academic_year' => 'next year', 'items' => [['name' => 'Tuition', 'amount' => 'lots']],
    ])->assertSessionHasErrors(['academic_year', 'items.0.amount']);
});

it('raises invoices in bulk, links enrolled parents and tells every family', function () {
    $this->actingAs($this->bursar)->get(route('fees.invoices.create'))->assertOk()->assertSee('Primary 1–3');

    $this->post(route('fees.invoices.store'), [
        'fee_structure_id' => $this->structure->id,
        'due_date' => now()->addWeeks(2)->toDateString(),
        'issue' => '1',
        'students' => "Amina Garba, Aisha Garba, 0803 555 0101\nYusuf Garba, Aisha Garba, 08035550101\nZainab Musa, Mal. Musa, 08057654321\nAmina Garba, Aisha Garba, 08035550101",
    ])->assertRedirect(route('fees.invoices.index'))->assertSessionHas('status');

    // The repeated "Amina Garba" line is skipped, not billed twice.
    $invoices = FeeInvoice::withoutGlobalScopes()->orderBy('id')->get();
    expect($invoices)->toHaveCount(3)
        ->and($invoices->pluck('total_kobo')->unique()->all())->toBe([150_000_00])
        ->and($invoices->pluck('status')->unique()->all())->toBe([InvoiceStatus::Issued])
        ->and($invoices[0]->guardian_user_id)->toBe($this->parent->id)
        ->and($invoices[2]->guardian_user_id)->toBeNull()
        ->and($invoices[2]->guardian_phone)->toBe('+2348057654321')
        ->and($invoices[0]->lines)->toHaveCount(2)
        ->and($invoices[0]->reference)->toStartWith('FEE-'.now()->year.'-');

    // An enrolled parent is told in the app and by SMS; a phone-only parent gets the SMS.
    expect(smsTo($this->parent))->toHaveCount(2)
        ->and(smsTo($this->parent)[0])->toContain('Al-Ihsan Model Academy: fees of ₦150,000 for Amina Garba')
        ->and(collect($this->sms->sent)->where('to', '+2348057654321')->count())->toBe(1)
        ->and($this->parent->notifications()->count())->toBe(2);

    // Drafts stay private until issued.
    $this->post(route('fees.invoices.store'), [
        'fee_structure_id' => $this->structure->id,
        'due_date' => now()->addWeeks(2)->toDateString(),
        'students' => 'Hauwa Ali, Mrs. Ali, 08031112222',
    ])->assertSessionHasNoErrors();

    $draft = FeeInvoice::withoutGlobalScopes()->where('student_name', 'Hauwa Ali')->firstOrFail();
    expect($draft->status)->toBe(InvoiceStatus::Draft)
        ->and(collect($this->sms->sent)->where('to', '+2348031112222')->count())->toBe(0);

    $this->post(route('fees.invoices.issue', $draft))->assertSessionHas('status');
    expect($draft->fresh()->status)->toBe(InvoiceStatus::Issued)
        ->and(collect($this->sms->sent)->where('to', '+2348031112222')->count())->toBe(1);
});

it('validates the bulk list and keeps schools apart', function () {
    $other = memberSchool($this->org['kanoChapter'], ['name' => 'Elsewhere Academy']);
    $theirs = makeFeeStructure($other);

    $this->actingAs($this->bursar)->post(route('fees.invoices.store'), [
        'fee_structure_id' => $this->structure->id,
        'due_date' => now()->addWeek()->toDateString(),
        'students' => 'Amina Garba, Aisha, 12345',
    ])->assertSessionHasErrors('students');

    $this->post(route('fees.invoices.store'), [
        'fee_structure_id' => $theirs->id,
        'due_date' => now()->addWeek()->toDateString(),
        'students' => 'Amina Garba',
    ])->assertSessionHasErrors('fee_structure_id');

    expect(FeeInvoice::withoutGlobalScopes()->count())->toBe(0);

    $foreign = makeFeeInvoice(5, ['school' => $other, 'guardian_user_id' => null]);
    $this->get(route('fees.invoices.show', $foreign))->assertNotFound();
    $this->post(route('fees.invoices.payments.store', $foreign), ['amount' => '1000', 'method' => 'cash'])->assertNotFound();
});

it('filters the invoice list by status tab and loads tabs in place', function () {
    makeFeeInvoice(10, ['student_name' => 'Issued Child']);
    makeFeeInvoice(-20, ['student_name' => 'Late Child']);
    makeFeeInvoice(5, ['student_name' => 'Paid Child', 'paid_kobo' => 150_000_00, 'status' => InvoiceStatus::Paid]);
    makeFeeInvoice(-5, ['student_name' => 'Part Child', 'paid_kobo' => 50_000_00, 'status' => InvoiceStatus::Overdue]);
    makeFeeInvoice(0, ['student_name' => 'Draft Child', 'status' => InvoiceStatus::Draft]);

    $this->actingAs($this->bursar);
    $this->get(route('fees.invoices.index'))->assertOk()
        ->assertSee('Arrears by age')->assertSee('Issued Child')->assertSee('Draft Child');

    $ajax = ['X-Requested-With' => 'XMLHttpRequest'];

    $overdue = $this->get(route('fees.invoices.index', ['status' => 'overdue']), $ajax)->assertOk();
    $overdue->assertSee('Late Child')->assertSee('Part Child')->assertDontSee('Issued Child')->assertDontSee('Paid Child')
        ->assertDontSee('Arrears by age')->assertSee('data-ajax-results', false);

    $this->get(route('fees.invoices.index', ['status' => 'paid']), $ajax)->assertSee('Paid Child')->assertDontSee('Late Child');
    $this->get(route('fees.invoices.index', ['status' => 'unpaid']), $ajax)->assertSee('Issued Child')->assertSee('Part Child')->assertDontSee('Draft Child')->assertDontSee('Paid Child');
    $this->get(route('fees.invoices.index', ['q' => 'garba']), $ajax)->assertSee('Issued Child');
    $this->get(route('fees.invoices.index', ['status' => 'overdue', 'q' => 'nobody']), $ajax)->assertSee('No invoices match');

    // Counts on the tabs: 5 all, 3 unpaid (open), 2 overdue, 1 paid.
    $html = $this->get(route('fees.invoices.index'), $ajax)->getContent();
    expect($html)->toMatch('/data-ajax-tab="all".*?>\s*All\s*<span[^>]*>5</s')
        ->and($html)->toMatch('/data-ajax-tab="overdue".*?>\s*Overdue\s*<span[^>]*>2</s');

    // Parents have no business here.
    $this->actingAs($this->parent)->get(route('fees.invoices.index'))->assertForbidden();
});

it('records cash for the bursar, part by part, and refuses more than is owed', function () {
    $invoice = makeFeeInvoice(10);

    $this->actingAs($this->bursar)->get(route('fees.invoices.show', $invoice))->assertOk()->assertSee('Record a payment')->assertSee('₦150,000');

    $this->post(route('fees.invoices.payments.store', $invoice), ['amount' => '50,000', 'method' => 'cash', 'note' => 'Brought by uncle'])
        ->assertSessionHasNoErrors()->assertSessionHas('status');

    $invoice->refresh();
    $payment = FeePayment::query()->sole();
    expect($invoice->status)->toBe(InvoiceStatus::Partial)
        ->and($invoice->paid_kobo)->toBe(50_000_00)
        ->and($invoice->balanceKobo())->toBe(100_000_00)
        ->and($payment->method)->toBe(FeePaymentMethod::Cash)
        ->and($payment->recorded_by)->toBe($this->bursar->id)
        ->and($payment->meta['note'])->toBe('Brought by uncle')
        ->and(collect($this->sms->sent)->where('to', $this->parent->phone)->last()['message'])->toContain('received ₦50,000')->toContain('Balance: ₦100,000');

    $this->post(route('fees.invoices.payments.store', $invoice), ['amount' => '100,000.01', 'method' => 'transfer'])
        ->assertSessionHasErrors('amount');
    $this->post(route('fees.invoices.payments.store', $invoice), ['amount' => '100,000', 'method' => 'paystack'])
        ->assertSessionHasErrors('method');
    expect($invoice->fresh()->paid_kobo)->toBe(50_000_00);

    $this->post(route('fees.invoices.payments.store', $invoice), ['amount' => '100,000', 'method' => 'teller', 'reference' => 'TLR-8841']);
    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->fresh()->balanceKobo())->toBe(0)
        ->and(FeePayment::query()->latest('id')->first()->reference)->toBe('TLR-8841');

    // Nothing more can be recorded on a paid invoice.
    $this->post(route('fees.invoices.payments.store', $invoice), ['amount' => '1', 'method' => 'cash'])->assertSessionHasErrors('amount');

    // A parent cannot record money, even their own.
    $this->actingAs($this->parent)->post(route('fees.invoices.payments.store', makeFeeInvoice()), ['amount' => '1', 'method' => 'cash'])->assertNotFound();
});

it('takes an online payment from the parent and records it exactly once (FEE-2)', function () {
    $invoice = makeFeeInvoice(10);

    $this->actingAs($this->parent)->get(route('fees.parent.show', $invoice))->assertOk()->assertSee('Pay ₦150,000 online');

    $response = $this->post(route('fees.parent.pay', $invoice), ['amount' => '50,000']);
    $transaction = GatewayTransaction::query()->sole();
    $response->assertRedirect($transaction->authorization_url);
    expect($transaction->purpose)->toBe('fees.invoice')
        ->and($transaction->amount_kobo)->toBe(50_000_00)
        ->and($transaction->school_id)->toBe($this->school->id)
        ->and($transaction->payer_id)->toBe($this->parent->id)
        ->and($transaction->meta('invoice_id'))->toBe($invoice->id);

    // A second tap reuses the open checkout instead of opening another.
    $this->post(route('fees.parent.pay', $invoice), ['amount' => '50,000'])->assertRedirect($transaction->authorization_url);
    expect(GatewayTransaction::query()->count())->toBe(1);

    app(PaymentGateway::class)->setOutcome($transaction->reference, GatewayStatus::Success);
    $this->get(route('payments.callback', ['reference' => $transaction->reference]))
        ->assertRedirect(route('fees.parent.show', $invoice))
        ->assertSessionHas('status');

    $payment = FeePayment::query()->sole();
    expect($payment->method)->toBe(FeePaymentMethod::Paystack)
        ->and($payment->amount_kobo)->toBe(50_000_00)
        ->and($payment->payment_intent_id)->toBe($transaction->id)
        ->and($payment->recorded_by)->toBeNull()
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Partial)
        ->and($invoice->fresh()->paid_kobo)->toBe(50_000_00);

    // Confirmation is idempotent: refreshing and replaying the event never credits twice.
    app(PaymentService::class)->refresh($transaction->fresh());
    event(new PaymentConfirmed($transaction->fresh()));
    expect(FeePayment::query()->count())->toBe(1)->and($invoice->fresh()->paid_kobo)->toBe(50_000_00);

    // Pay the rest; more than the balance is refused.
    $this->post(route('fees.parent.pay', $invoice), ['amount' => '100,000.01'])->assertSessionHas('error');
    $this->post(route('fees.parent.pay', $invoice));
    $second = GatewayTransaction::query()->latest('id')->first();
    expect($second->amount_kobo)->toBe(100_000_00);

    app(PaymentGateway::class)->setOutcome($second->reference, GatewayStatus::Success);
    $this->get(route('payments.callback', ['reference' => $second->reference]));

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and(FeePayment::query()->count())->toBe(2);

    // Nothing left to pay.
    $this->post(route('fees.parent.pay', $invoice))->assertSessionHas('error');
});

it('shows a parent only their own invoices (FEE-3)', function () {
    $mine = makeFeeInvoice(10, ['student_name' => 'My Child']);
    $draft = makeFeeInvoice(10, ['student_name' => 'Draft Child', 'status' => InvoiceStatus::Draft]);
    $stranger = User::factory()->create();
    RoleAssignment::grant($stranger, Role::Parent, ScopeLevel::Personal);
    $theirs = makeFeeInvoice(10, ['student_name' => 'Stranger Child', 'guardian_user_id' => $stranger->id]);

    $this->actingAs($this->parent)->get(route('fees.parent.index'))->assertOk()
        ->assertSee('My Child')->assertSee('₦150,000')->assertDontSee('Stranger Child')->assertDontSee('Draft Child');

    $this->get(route('fees.parent.show', $mine))->assertOk()->assertSee('Al-Ihsan Model Academy')->assertSee('Tuition');
    $this->get(route('fees.parent.show', $theirs))->assertNotFound();
    $this->get(route('fees.parent.show', $draft))->assertNotFound();
    $this->post(route('fees.parent.pay', $theirs))->assertNotFound();

    // Staff cannot use the portal routes.
    $this->actingAs($this->bursar)->get(route('fees.parent.index'))->assertForbidden();
});

it('ages invoices into overdue and sends each reminder once (FEE-2)', function () {
    $due = makeFeeInvoice(3, ['student_name' => 'Due Soon']);
    $today = makeFeeInvoice(0, ['student_name' => 'Due Today', 'status' => InvoiceStatus::Issued]);
    $week = makeFeeInvoice(-7, ['student_name' => 'Week Late', 'status' => InvoiceStatus::Issued, 'guardian_user_id' => null]);
    $early = makeFeeInvoice(20, ['student_name' => 'Far Off']);
    $paid = makeFeeInvoice(-7, ['student_name' => 'Already Paid', 'status' => InvoiceStatus::Paid, 'paid_kobo' => 150_000_00]);

    $this->artisan('fees:send-reminders', ['--now' => true])->expectsOutputToContain('1 invoice(s) marked overdue, 3 reminder(s) sent.')->assertSuccessful();

    // Due today is not late yet; a week late is.
    expect($today->fresh()->status)->toBe(InvoiceStatus::Issued)
        ->and($week->fresh()->status)->toBe(InvoiceStatus::Overdue)
        ->and($due->fresh()->status)->toBe(InvoiceStatus::Issued)
        ->and($due->reminders()->sole()->kind)->toBe(ReminderKind::Before)
        ->and($today->reminders()->sole()->kind)->toBe(ReminderKind::On)
        ->and($week->reminders()->sole()->kind)->toBe(ReminderKind::Week)
        ->and($early->reminders()->count())->toBe(0)
        ->and($paid->reminders()->count())->toBe(0);

    expect(smsTo($this->parent)[0])->toContain('fall due on')
        ->and(collect($this->sms->sent)->where('to', $this->parent->phone)->pluck('message')->implode(' '))->toContain('are due today')
        // The week-late invoice has only the phone number on it, which is the same parent's.
        ->and(collect($this->sms->sent)->where('to', $this->parent->phone))->toHaveCount(3);

    // Running again the same day sends nothing new.
    $this->artisan('fees:send-reminders', ['--now' => true])->expectsOutputToContain('0 invoice(s) marked overdue, 0 reminder(s) sent.')->assertSuccessful();
    expect(FeeReminder::query()->count())->toBe(3);

    // Queued by default.
    $this->artisan('fees:send-reminders')->expectsOutputToContain('queued')->assertSuccessful();
});

it('leaves families alone who did not consent to reminders', function () {
    $invoice = makeFeeInvoice(-14, ['status' => InvoiceStatus::Overdue]);

    FeeAgreement::withoutGlobalScopes()->create([
        'school_id' => $this->school->id,
        'guardian_user_id' => $this->parent->id,
        'student_name' => 'Amina Garba',
        'signed_at' => now(),
        'consent_reminders' => false,
        'consent_debtor_check' => false,
    ]);

    expect(app(FeeInvoiceService::class)->sendScheduledReminders()['sent'])->toBe(0)
        ->and(FeeReminder::query()->count())->toBe(0);

    $this->actingAs($this->bursar)->post(route('fees.invoices.remind', $invoice))->assertSessionHas('error');
    $this->get(route('fees.invoices.show', $invoice))->assertSee('did not consent to SMS reminders');

    FeeAgreement::withoutGlobalScopes()->update(['consent_reminders' => true]);
    $this->post(route('fees.invoices.remind', $invoice))->assertSessionHas('status');
    expect($invoice->reminders()->sole()->kind)->toBe(ReminderKind::Fortnight);
});

it('lets the bursar remind every overdue family at once, skipping recent reminders', function () {
    $a = makeFeeInvoice(-10, ['student_name' => 'Late A', 'status' => InvoiceStatus::Overdue]);
    $b = makeFeeInvoice(-10, ['student_name' => 'Late B', 'status' => InvoiceStatus::Overdue]);
    FeeReminder::query()->create(['fee_invoice_id' => $b->id, 'channel' => 'sms', 'sent_at' => now()->subDay(), 'kind' => ReminderKind::On]);

    $this->actingAs($this->bursar)->post(route('fees.invoices.remind-overdue'))
        ->assertSessionHas('status', '1 reminders sent, 1 skipped (reminded recently, no phone number or opted out).');

    expect($a->reminders()->count())->toBe(1)->and($b->reminders()->count())->toBe(1);
});

it('shows arrears ageing in the right buckets', function () {
    makeFeeInvoice(5, ['total_kobo' => 10_000_00]);
    makeFeeInvoice(-1, ['total_kobo' => 20_000_00, 'paid_kobo' => 5_000_00, 'status' => InvoiceStatus::Partial]);
    makeFeeInvoice(-30, ['total_kobo' => 30_000_00]);
    makeFeeInvoice(-31, ['total_kobo' => 40_000_00]);
    makeFeeInvoice(-75, ['total_kobo' => 50_000_00]);
    makeFeeInvoice(-90, ['total_kobo' => 60_000_00]);
    makeFeeInvoice(-91, ['total_kobo' => 70_000_00]);
    makeFeeInvoice(-200, ['total_kobo' => 80_000_00, 'status' => InvoiceStatus::Paid, 'paid_kobo' => 80_000_00]);
    makeFeeInvoice(-200, ['total_kobo' => 99_000_00, 'status' => InvoiceStatus::Void]);

    $this->actingAs($this->bursar);

    $buckets = collect(app(FeeInvoiceService::class)->ageing())->keyBy('key');
    expect($buckets['current']['kobo'])->toBe(10_000_00)
        ->and($buckets['1-30']['kobo'])->toBe(15_000_00 + 30_000_00)
        ->and($buckets['1-30']['count'])->toBe(2)
        ->and($buckets['31-60']['kobo'])->toBe(40_000_00)
        ->and($buckets['61-90']['kobo'])->toBe(50_000_00 + 60_000_00)
        ->and($buckets['90+']['kobo'])->toBe(70_000_00);

    // Only this school's money counts.
    $other = memberSchool($this->org['kanoChapter']);
    makeFeeInvoice(-100, ['school' => $other, 'guardian_user_id' => null, 'total_kobo' => 1_000_000_00]);
    expect(collect(app(FeeInvoiceService::class)->ageing())->keyBy('key')['90+']['kobo'])->toBe(70_000_00);

    // Dashboard widgets: arrears for the school, "fees to pay" for the parent.
    $this->actingAs($this->proprietor)->get(route('dashboard'))->assertOk()->assertSee('Fee arrears')->assertSee('Over 90 days');
    $this->actingAs($this->parent)->get(route('dashboard'))->assertOk()->assertSee('Fees to pay')->assertSee('My fees');
    $this->actingAs($this->bursar)->get(route('dashboard'))->assertOk()->assertSee(route('fees.invoices.index'), false);
});

it('escalates an invoice more than 90 days overdue to a claim', function () {
    StateClaimSetting::query()->create(['state_id' => $this->org['kano']->id, 'enabled' => true, 'barrister_confirmed_at' => now()]);

    $recent = makeFeeInvoice(-60, ['student_name' => 'Recent Debt']);
    $old = makeFeeInvoice(-100, ['student_name' => 'Old Debt', 'paid_kobo' => 50_000_00, 'guardian_name' => 'Mrs. Grace Okonkwo', 'guardian_phone' => '+2348024119002']);

    $this->actingAs($this->proprietor)->get(route('fees.invoices.show', $recent))->assertOk()->assertDontSee('Escalate to a claim');
    $this->post(route('fees.invoices.escalate', $recent))->assertSessionHas('error');
    expect($recent->fresh()->claim_reference)->toBeNull();

    $this->get(route('fees.invoices.show', $old))->assertOk()->assertSee('Escalate to a claim');
    $this->post(route('fees.invoices.escalate', $old))->assertSessionHas('status');

    $old->refresh();
    $case = ClaimCase::query()->sole();
    expect($old->claim_reference)->toBe($case->reference)
        ->and($old->escalated_at)->not->toBeNull()
        ->and($case->amount_kobo)->toBe(100_000_00)
        ->and($case->debtor_name)->toBe('Mrs. Grace Okonkwo')
        ->and($case->debtor_phone)->toBe('+2348024119002')
        ->and($case->days_overdue)->toBe(100)
        ->and($case->school_id)->toBe($this->school->id)
        ->and($case->summary)->toContain('Old Debt')
        ->and($case->opened_by)->toBe($this->proprietor->id);

    // Only once.
    $this->get(route('fees.invoices.show', $old))->assertDontSee('Escalate to a claim')->assertSee($case->reference);
    $this->post(route('fees.invoices.escalate', $old))->assertSessionHas('error');
    expect(ClaimCase::query()->count())->toBe(1);

    // Bursars cannot open claims; states without claims switched on say so.
    $another = makeFeeInvoice(-120, ['student_name' => 'Another Debt']);
    $this->actingAs($this->bursar)->post(route('fees.invoices.escalate', $another))->assertForbidden();

    StateClaimSetting::query()->update(['enabled' => false]);
    $this->actingAs($this->proprietor)->post(route('fees.invoices.escalate', $another))->assertSessionHas('error');
    expect($another->fresh()->claim_reference)->toBeNull();
});

it('voids unpaid invoices but never ones with payments', function () {
    $clean = makeFeeInvoice(10);
    $paidOn = makeFeeInvoice(10, ['paid_kobo' => 1_000_00, 'status' => InvoiceStatus::Partial]);

    $this->actingAs($this->bursar)->post(route('fees.invoices.void', $clean))->assertSessionHas('status');
    $this->post(route('fees.invoices.void', $paidOn))->assertSessionHas('error');

    expect($clean->fresh()->status)->toBe(InvoiceStatus::Void)
        ->and($paidOn->fresh()->status)->toBe(InvoiceStatus::Partial);

    // A voided invoice leaves the parent's list.
    $this->actingAs($this->parent)->get(route('fees.parent.show', $clean))->assertNotFound();
});

it('lets a parent sign a fee agreement with a school that billed them (FEE-4)', function () {
    makeFeeInvoice(10);

    $this->actingAs($this->parent)->get(route('fees.agreements.create'))->assertOk()->assertSee('Al-Ihsan Model Academy')->assertSee('Amina Garba');

    $this->post(route('fees.agreements.store'), [
        'school_id' => $this->school->id,
        'student_name' => 'Amina Garba',
        'signature' => 'Aisha Garba',
    ])->assertSessionHasErrors('accept');

    $other = memberSchool($this->org['kanoChapter']);
    $this->post(route('fees.agreements.store'), [
        'school_id' => $other->id, 'student_name' => 'Amina Garba', 'signature' => 'Aisha Garba', 'accept' => '1',
    ])->assertSessionHasErrors('school_id');

    $this->post(route('fees.agreements.store'), [
        'school_id' => $this->school->id,
        'student_name' => 'Amina Garba',
        'signature' => 'Aisha Garba',
        'accept' => '1',
        'consent_reminders' => '1',
    ])->assertRedirect(route('fees.parent.index'))->assertSessionHas('status');

    $agreement = FeeAgreement::withoutGlobalScopes()->sole();
    expect($agreement->guardian_user_id)->toBe($this->parent->id)
        ->and($agreement->school_id)->toBe($this->school->id)
        ->and($agreement->consent_reminders)->toBeTrue()
        ->and($agreement->consent_debtor_check)->toBeFalse()
        ->and($agreement->pdf_path)->toStartWith('fees/agreements/');

    Storage::disk('local')->assertExists($agreement->pdf_path);

    $this->get(route('fees.agreements.show', $agreement))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->get(route('fees.parent.index'))->assertSee('Signed agreements');

    // The school's staff can read it; another parent and other schools cannot.
    $this->actingAs($this->bursar)->get(route('fees.agreements.index'))->assertOk()->assertSee('Amina Garba')->assertSee('Aisha Garba');
    $this->get(route('fees.agreements.show', $agreement))->assertOk();

    $stranger = User::factory()->create();
    RoleAssignment::grant($stranger, Role::Parent, ScopeLevel::Personal);
    $this->actingAs($stranger)->get(route('fees.agreements.show', $agreement))->assertForbidden();

    $elsewhere = officer(Role::Bursar, $other->id);
    $this->actingAs($elsewhere)->get(route('fees.agreements.show', $agreement))->assertForbidden();
    $this->get(route('fees.agreements.index'))->assertOk()->assertDontSee('Amina Garba');
});

it('puts Fees in the right navigation', function () {
    $this->actingAs($this->bursar)->get(route('dashboard'))->assertOk()->assertSee(route('fees.invoices.index'), false);
    $this->actingAs($this->parent)->get(route('dashboard'))->assertOk()->assertSee(route('fees.parent.index'), false)->assertDontSee(route('fees.invoices.index'), false);
});
