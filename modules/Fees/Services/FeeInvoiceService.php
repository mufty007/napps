<?php

namespace Modules\Fees\Services;

use App\Enums\GatewayStatus;
use App\Enums\Role;
use App\Models\GatewayTransaction;
use App\Models\School;
use App\Models\User;
use App\Services\Payments\PaymentService;
use App\Support\Money;
use App\Support\PhoneNumber;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Modules\Fees\Enums\FeePaymentMethod;
use Modules\Fees\Enums\InvoiceStatus;
use Modules\Fees\Enums\ReminderKind;
use Modules\Fees\Models\FeeAgreement;
use Modules\Fees\Models\FeeInvoice;
use Modules\Fees\Models\FeePayment;
use Modules\Fees\Models\FeeReminder;
use Modules\Fees\Models\FeeStructure;
use Modules\Fees\Notifications\FeeInvoiceIssued;
use Modules\Fees\Notifications\FeePaymentReceived;
use Modules\Fees\Notifications\FeeReminderDue;

/**
 * Everything that changes an invoice goes through here: raising and issuing
 * it, taking money (online through the kernel PaymentService, or recorded by
 * the bursar), ageing it into overdue, reminding the family and, past 90
 * days, handing it to the Claims module.
 */
class FeeInvoiceService
{
    public const PURPOSE = 'fees.invoice';

    /** Claims is optional; Fees talks to it by name only. */
    public const CLAIMS_SERVICE = 'Modules\\Claims\\Services\\ClaimService';

    public function __construct(private readonly PaymentService $payments) {}

    /**
     * Raise one draft invoice. The total comes from the structure's items
     * (snapshotted onto the invoice) or from an explicit total_kobo.
     *
     * @param  array{student_name: string, due_date: CarbonInterface|string, guardian_name?: ?string, guardian_phone?: ?string, guardian_user_id?: ?int, total_kobo?: ?int, notes?: ?string, student_id?: ?int}  $data
     */
    public function create(School $school, array $data, ?FeeStructure $structure = null): FeeInvoice
    {
        $lines = null;
        $total = $data['total_kobo'] ?? null;

        if ($structure !== null) {
            $structure->loadMissing('items');
            $lines = $structure->items->map(fn ($item) => ['name' => $item->name, 'amount_kobo' => $item->amount_kobo])->values()->all();
            $total = (int) $structure->items->sum('amount_kobo');
        }

        if ($total === null || $total <= 0) {
            throw new DomainException(__('An invoice needs a fee structure with at least one item, or a total.'));
        }

        $phone = PhoneNumber::normalize($data['guardian_phone'] ?? null) ?? ($data['guardian_phone'] ?? null);
        $guardian = isset($data['guardian_user_id'])
            ? User::query()->find($data['guardian_user_id'])
            : $this->resolveGuardian($phone);

        return FeeInvoice::withoutGlobalScopes()->create([
            'school_id' => $school->getKey(),
            'student_id' => $data['student_id'] ?? null,
            'student_name' => trim($data['student_name']),
            'guardian_user_id' => $guardian?->getKey(),
            'guardian_name' => $this->nullIfBlank($data['guardian_name'] ?? null) ?? $guardian?->name,
            'guardian_phone' => $this->nullIfBlank($phone),
            'fee_structure_id' => $structure?->getKey(),
            'reference' => $this->nextReference(),
            'lines' => $lines,
            'total_kobo' => $total,
            'paid_kobo' => 0,
            'due_date' => $data['due_date'],
            'status' => InvoiceStatus::Draft,
            'notes' => $this->nullIfBlank($data['notes'] ?? null),
        ]);
    }

    /**
     * Raise an invoice per child from a fee structure. A child who already
     * has a live invoice on this structure is skipped, so resubmitting a
     * pasted list never bills a family twice.
     *
     * @param  list<array{student_name: string, guardian_name?: ?string, guardian_phone?: ?string}>  $students
     * @return array{created: Collection<int, FeeInvoice>, skipped: int}
     */
    public function bulkCreate(
        School $school,
        FeeStructure $structure,
        CarbonInterface $dueDate,
        array $students,
        ?string $notes = null,
        bool $issue = true,
    ): array {
        $structure->loadMissing('items');

        if ($structure->items->isEmpty()) {
            throw new DomainException(__('This fee structure has no items yet.'));
        }

        $existing = FeeInvoice::withoutGlobalScopes()
            ->where('school_id', $school->getKey())
            ->where('fee_structure_id', $structure->getKey())
            ->where('status', '!=', InvoiceStatus::Void->value)
            ->pluck('student_name')
            ->map(fn (string $name) => Str::lower($name))
            ->flip();

        $skipped = 0;

        $created = DB::transaction(function () use ($school, $structure, $dueDate, $students, $notes, $existing, &$skipped) {
            $created = new Collection;

            foreach ($students as $student) {
                $key = Str::lower(trim($student['student_name']));

                if ($key === '' || $existing->has($key)) {
                    $skipped++;

                    continue;
                }

                $existing->put($key, true);

                $created->push($this->create($school, [...$student, 'due_date' => $dueDate, 'notes' => $notes], $structure));
            }

            return $created;
        });

        if ($issue) {
            $created->each(fn (FeeInvoice $invoice) => $this->issue($invoice));
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * Send a draft to the family: it becomes payable and they are told by SMS.
     */
    public function issue(FeeInvoice $invoice): FeeInvoice
    {
        if ($invoice->status !== InvoiceStatus::Draft) {
            throw new DomainException(__('Only a draft invoice can be issued.'));
        }

        $invoice->forceFill([
            'status' => $invoice->due_date->lt(today()) ? InvoiceStatus::Overdue : InvoiceStatus::Issued,
            'issued_at' => now(),
        ])->save();

        $this->notifyGuardian($invoice, new FeeInvoiceIssued($invoice, $this->schoolName($invoice)));

        return $invoice;
    }

    /**
     * Cancel an invoice nobody has paid. Paid invoices stay on the books.
     */
    public function void(FeeInvoice $invoice): FeeInvoice
    {
        if ($invoice->status === InvoiceStatus::Void) {
            return $invoice;
        }

        if ($invoice->paid_kobo > 0) {
            throw new DomainException(__('This invoice has payments on it, so it cannot be voided.'));
        }

        $invoice->forceFill(['status' => InvoiceStatus::Void])->save();

        return $invoice;
    }

    /**
     * Open (or reuse) an online checkout for an invoice. The amount defaults
     * to the full balance; families may pay part of it.
     *
     * @throws DomainException when there is nothing to pay or the amount is too high
     */
    public function startOnlinePayment(FeeInvoice $invoice, User $payer, ?int $amountKobo, string $returnUrl): GatewayTransaction
    {
        $balance = $invoice->balanceKobo();

        if (! $invoice->isOpen() || $balance <= 0) {
            throw new DomainException(__('This invoice has nothing left to pay.'));
        }

        $amount = $amountKobo ?? $balance;

        if ($amount <= 0 || $amount > $balance) {
            throw new DomainException(__('Enter an amount between ₦1 and the balance of :balance.', ['balance' => Money::format($balance)]));
        }

        // A second tap on "Pay" within half an hour returns to the same checkout
        // instead of opening one that could be paid twice.
        $existing = GatewayTransaction::query()
            ->where('purpose', self::PURPOSE)
            ->where('school_id', $invoice->school_id)
            ->where('payer_id', $payer->getKey())
            ->where('status', GatewayStatus::Pending)
            ->where('amount_kobo', $amount)
            ->whereNotNull('authorization_url')
            ->where('created_at', '>=', now()->subMinutes(30))
            ->latest('id')
            ->get()
            ->first(fn (GatewayTransaction $t) => $t->meta('invoice_id') === $invoice->getKey());

        return $existing ?? $this->payments->start(self::PURPOSE, $amount, $payer, $invoice->school_id, $returnUrl, [
            'invoice_id' => $invoice->getKey(),
            'invoice_reference' => $invoice->reference,
            'student_name' => $invoice->student_name,
        ]);
    }

    /**
     * Record money received against an invoice and move its status on.
     *
     * Online payments (a gateway transaction is passed) are always recorded:
     * the money has been taken, so any excess is noted for refund instead of
     * failing the confirmation. Offline payments cannot exceed the balance.
     * Recording the same gateway transaction twice returns the first payment.
     *
     * @param  array<string, mixed>  $meta
     *
     * @throws DomainException
     */
    public function recordPayment(
        FeeInvoice $invoice,
        int $amountKobo,
        FeePaymentMethod $method,
        ?User $recordedBy = null,
        ?GatewayTransaction $transaction = null,
        ?CarbonInterface $paidAt = null,
        ?string $reference = null,
        array $meta = [],
    ): FeePayment {
        if ($amountKobo <= 0) {
            throw new DomainException(__('Enter an amount greater than zero.'));
        }

        $paidAt ??= now();

        $payment = DB::transaction(function () use ($invoice, $amountKobo, $method, $recordedBy, $transaction, $paidAt, $reference, $meta) {
            if ($transaction !== null && ($existing = FeePayment::query()->where('payment_intent_id', $transaction->getKey())->first())) {
                return $existing;
            }

            $locked = FeeInvoice::withoutGlobalScopes()->lockForUpdate()->findOrFail($invoice->getKey());
            $balance = $locked->balanceKobo();

            if ($transaction === null) {
                if (! $locked->isOpen()) {
                    throw new DomainException(__('This invoice is not open for payment.'));
                }

                if ($amountKobo > $balance) {
                    throw new DomainException(__('That is more than the balance of :amount.', ['amount' => Money::format($balance)]));
                }
            } elseif ($amountKobo > $balance || ! $locked->isOpen()) {
                $meta['excess_kobo'] = $locked->isOpen() ? $amountKobo - $balance : $amountKobo;
                $meta['refund_due'] = true;
            }

            $payment = FeePayment::query()->create([
                'fee_invoice_id' => $locked->getKey(),
                'amount_kobo' => $amountKobo,
                'method' => $method,
                'reference' => $reference ?? $transaction?->reference,
                'recorded_by' => $recordedBy?->getKey(),
                'payment_intent_id' => $transaction?->getKey(),
                'paid_at' => $paidAt,
                'meta' => $meta === [] ? null : $meta,
            ]);

            $paid = $locked->paid_kobo + $amountKobo;

            $locked->forceFill([
                'paid_kobo' => $paid,
                'status' => match (true) {
                    $locked->status === InvoiceStatus::Void => InvoiceStatus::Void,
                    $paid >= $locked->total_kobo => InvoiceStatus::Paid,
                    $locked->due_date->lt(today()) => InvoiceStatus::Overdue,
                    default => InvoiceStatus::Partial,
                },
            ])->save();

            $invoice->setRawAttributes($locked->getAttributes(), true);

            return $payment;
        });

        if ($payment->wasRecentlyCreated) {
            DB::afterCommit(fn () => $this->notifyGuardian($invoice, new FeePaymentReceived($invoice, $payment, $this->schoolName($invoice))));
        }

        return $payment;
    }

    /**
     * Flip issued and part-paid invoices past their due date to Overdue.
     * Safe to run any number of times.
     *
     * @return int Invoices changed.
     */
    public function markOverdue(): int
    {
        return FeeInvoice::withoutGlobalScopes()
            ->whereIn('status', [InvoiceStatus::Issued->value, InvoiceStatus::Partial->value])
            ->where('due_date', '<', today()->toDateString())
            ->update(['status' => InvoiceStatus::Overdue->value, 'updated_at' => now()]);
    }

    public function claimsAvailable(): bool
    {
        return class_exists(self::CLAIMS_SERVICE);
    }

    /**
     * An open invoice more than 90 days past due that has not yet become a claim.
     */
    public function canEscalate(FeeInvoice $invoice): bool
    {
        return $this->claimsAvailable()
            && ! $invoice->isEscalated()
            && $invoice->daysOverdue() > FeeInvoice::ESCALATION_DAYS;
    }

    /**
     * Hand an invoice more than 90 days overdue to the Claims module as a
     * new claim for the balance. Returns the claim reference.
     *
     * @throws DomainException when Claims is missing or switched off for the state, or the invoice is not yet 90 days overdue
     */
    public function escalateToClaim(FeeInvoice $invoice, User $by): string
    {
        if (! $this->claimsAvailable()) {
            throw new DomainException(__('Debt claims are not available yet.'));
        }

        if ($invoice->isEscalated()) {
            throw new DomainException(__('This invoice is already with claim :ref.', ['ref' => $invoice->claim_reference]));
        }

        if ($invoice->daysOverdue() <= FeeInvoice::ESCALATION_DAYS) {
            throw new DomainException(__('An invoice can become a debt claim once it is more than :days days overdue.', ['days' => FeeInvoice::ESCALATION_DAYS]));
        }

        $invoice->loadMissing('guardian');
        $school = School::withoutGlobalScopes()->with('state')->findOrFail($invoice->school_id);

        $case = app(self::CLAIMS_SERVICE)->openCase($by, $school, [
            'debtor_name' => $invoice->guardianLabel(),
            'debtor_phone' => $invoice->guardian_phone ?? $invoice->guardian?->phone,
            'amount_kobo' => $invoice->balanceKobo(),
            'days_overdue' => $invoice->daysOverdue(),
            'summary' => __('Fees for :student (invoice :ref): :total billed, :paid paid, due :date. :count reminders sent.', [
                'student' => $invoice->student_name,
                'ref' => $invoice->reference,
                'total' => Money::format($invoice->total_kobo),
                'paid' => Money::format($invoice->paid_kobo),
                'date' => $invoice->due_date->format('j M Y'),
                'count' => $invoice->reminders()->count(),
            ]),
        ]);

        $invoice->forceFill(['claim_reference' => $case->reference, 'escalated_at' => now()])->save();

        return (string) $case->reference;
    }

    /**
     * The reminder due today for an invoice, if any and not yet sent.
     */
    public function nextReminder(FeeInvoice $invoice, ?CarbonInterface $today = null): ?ReminderKind
    {
        if (! $invoice->isOpen() || $invoice->balanceKobo() <= 0) {
            return null;
        }

        $offset = $this->offsetDays($invoice, $today);
        $sent = $invoice->reminders->pluck('kind')->all();

        foreach (ReminderKind::cases() as $kind) {
            if ($kind->covers($offset) && ! in_array($kind, $sent, true)) {
                return $kind;
            }
        }

        return null;
    }

    /**
     * Whether the family may be reminded: a guardian who signed an agreement
     * without consenting to reminders is left alone.
     */
    public function remindersAllowed(FeeInvoice $invoice): bool
    {
        if ($invoice->guardian_user_id === null) {
            return true;
        }

        $agreement = FeeAgreement::withoutGlobalScopes()
            ->where('school_id', $invoice->school_id)
            ->where('guardian_user_id', $invoice->guardian_user_id)
            ->latest('signed_at')
            ->latest('id')
            ->first();

        return $agreement === null || $agreement->consent_reminders;
    }

    /**
     * Text the family and log it. Returns false when nobody can be reached
     * or the family opted out of reminders.
     */
    public function sendReminder(FeeInvoice $invoice, ReminderKind $kind): bool
    {
        if (! $invoice->isOpen() || $invoice->balanceKobo() <= 0 || ! $this->remindersAllowed($invoice)) {
            return false;
        }

        if (! $this->notifyGuardian($invoice, new FeeReminderDue($invoice, $kind, $this->schoolName($invoice)))) {
            return false;
        }

        FeeReminder::query()->create([
            'fee_invoice_id' => $invoice->getKey(),
            'channel' => 'sms',
            'sent_at' => now(),
            'kind' => $kind,
        ]);

        return true;
    }

    /**
     * A bursar's "send reminder now": picks the wording for how late it is.
     */
    public function remindNow(FeeInvoice $invoice): bool
    {
        return $this->sendReminder($invoice, ReminderKind::forOffset($this->offsetDays($invoice)));
    }

    /**
     * Run today's scheduled reminders for every school.
     *
     * @return array{overdue: int, sent: int}
     */
    public function sendScheduledReminders(?CarbonInterface $today = null): array
    {
        $today ??= today();
        $overdue = $this->markOverdue();
        $sent = 0;

        FeeInvoice::withoutGlobalScopes()
            ->unpaid()
            ->where('due_date', '>=', $today->copy()->subDays(32)->toDateString())
            ->where('due_date', '<', $today->copy()->addDays(4)->toDateString())
            ->with(['guardian', 'reminders'])
            ->chunkById(200, function (Collection $invoices) use ($today, &$sent) {
                foreach ($invoices as $invoice) {
                    $kind = $this->nextReminder($invoice, $today);

                    if ($kind !== null && $this->sendReminder($invoice, $kind)) {
                        $sent++;
                    }
                }
            });

        return ['overdue' => $overdue, 'sent' => $sent];
    }

    /**
     * Money still owed, by how late it is. Scoped to the active role.
     *
     * @return list<array{key: string, label: string, count: int, kobo: int}>
     */
    public function ageing(): array
    {
        $today = today();
        $day = fn (int $days) => $today->copy()->subDays($days)->toDateString();

        // Half-open ranges (>= lower, < upper) so a date stored with a midnight
        // time part (SQLite) still lands in exactly one bucket.
        $buckets = [
            ['current', __('Not yet due'), fn (Builder $q) => $q->where('due_date', '>=', $day(0))],
            ['1-30', __('1–30 days'), fn (Builder $q) => $q->where('due_date', '>=', $day(30))->where('due_date', '<', $day(0))],
            ['31-60', __('31–60 days'), fn (Builder $q) => $q->where('due_date', '>=', $day(60))->where('due_date', '<', $day(30))],
            ['61-90', __('61–90 days'), fn (Builder $q) => $q->where('due_date', '>=', $day(90))->where('due_date', '<', $day(60))],
            ['90+', __('Over 90 days'), fn (Builder $q) => $q->where('due_date', '<', $day(90))],
        ];

        $rows = [];

        foreach ($buckets as [$key, $label, $constrain]) {
            $query = FeeInvoice::query()->unpaid();
            $constrain($query);

            $row = $query->toBase()
                ->selectRaw('count(*) as invoice_count, coalesce(sum(total_kobo - paid_kobo), 0) as owed_kobo')
                ->first();

            $rows[] = [
                'key' => $key,
                'label' => $label,
                'count' => (int) ($row->invoice_count ?? 0),
                'kobo' => (int) ($row->owed_kobo ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * Whole days from the due date to today (negative before it is due).
     */
    private function offsetDays(FeeInvoice $invoice, ?CarbonInterface $today = null): int
    {
        $today = ($today ?? today())->copy()->startOfDay();

        return (int) round($invoice->due_date->copy()->startOfDay()->diffInDays($today, false));
    }

    /**
     * Notify the guardian: their account if linked, else the phone on the
     * invoice. Returns false when there is nobody to tell.
     */
    private function notifyGuardian(FeeInvoice $invoice, BaseNotification $notification): bool
    {
        $invoice->loadMissing('guardian');

        if ($invoice->guardian) {
            $invoice->guardian->notify($notification);

            return true;
        }

        if (filled($invoice->guardian_phone)) {
            Notification::route('sms', $invoice->guardian_phone)->notify($notification);

            return true;
        }

        return false;
    }

    private function resolveGuardian(?string $phone): ?User
    {
        if (blank($phone)) {
            return null;
        }

        $user = User::query()->where('phone', $phone)->first();

        return $user?->holdsRole(Role::Parent) ? $user : null;
    }

    private function schoolName(FeeInvoice $invoice): string
    {
        return (string) School::withoutGlobalScopes()->whereKey($invoice->school_id)->value('name');
    }

    private function nextReference(): string
    {
        do {
            $reference = sprintf('FEE-%d-%s', now()->year, strtoupper(Str::random(8)));
        } while (FeeInvoice::withoutGlobalScopes()->where('reference', $reference)->exists());

        return $reference;
    }

    private function nullIfBlank(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
