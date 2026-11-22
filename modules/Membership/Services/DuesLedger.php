<?php

namespace Modules\Membership\Services;

use App\Enums\GatewayStatus;
use App\Enums\Role;
use App\Enums\ScopeLevel;
use App\Models\GatewayTransaction;
use App\Models\School;
use App\Models\User;
use App\Services\Payments\PaymentService;
use App\Support\Money;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Modules\Membership\Enums\DueStatus;
use Modules\Membership\Enums\PaymentMethod;
use Modules\Membership\Jobs\IssueDuesCharge;
use Modules\Membership\Models\DuesAllocation;
use Modules\Membership\Models\DuesCharge;
use Modules\Membership\Models\DuesPayment;
use Modules\Membership\Models\MembershipDue;
use Modules\Membership\Notifications\DuesPaymentReceived;

/**
 * Dues money: billing schools for charges, taking payments (online through
 * the kernel PaymentService, or recorded by an executive), issuing receipt
 * numbers and writing the split ledger.
 */
class DuesLedger
{
    public const PURPOSE = 'membership.dues';

    public function __construct(
        private readonly ComplianceService $compliance,
        private readonly PaymentService $payments,
    ) {}

    /**
     * Lock the charge and bill every school under it (in the background).
     */
    public function issue(DuesCharge $charge, User $by): void
    {
        if ($charge->isIssued() || $charge->isCancelled()) {
            throw new DomainException(__('This charge has already been issued or withdrawn.'));
        }

        $charge->update(['issued_at' => now(), 'issued_by' => $by->getKey()]);

        IssueDuesCharge::dispatch($charge);
    }

    /**
     * Create the dues rows for every approved school under an issued charge.
     * Safe to run again: schools already billed are skipped.
     *
     * @return list<int> Ids of schools billed by this run.
     */
    public function bill(DuesCharge $charge): array
    {
        if (! $charge->isIssued() || $charge->isCancelled()) {
            return [];
        }

        $billed = [];

        $charge->schoolsQuery()->select('id')->chunkById(500, function (Collection $schools) use ($charge, &$billed) {
            $already = MembershipDue::withoutGlobalScopes()
                ->where('dues_charge_id', $charge->getKey())
                ->whereIn('school_id', $schools->modelKeys())
                ->pluck('school_id')
                ->all();

            $now = now();
            $rows = $schools->reject(fn (School $s) => in_array($s->getKey(), $already, true))
                ->map(fn (School $s) => [
                    'school_id' => $s->getKey(),
                    'dues_charge_id' => $charge->getKey(),
                    'amount_kobo' => $charge->amount_kobo,
                    'due_date' => $charge->due_date->toDateString(),
                    'status' => DueStatus::Unpaid->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->values();

            if ($rows->isNotEmpty()) {
                MembershipDue::withoutGlobalScopes()->insertOrIgnore($rows->all());
                array_push($billed, ...$rows->pluck('school_id')->all());
            }
        });

        return $billed;
    }

    /**
     * A newly approved member pays this year's (and any already issued
     * future) dues set over it.
     */
    public function billNewMember(School $school): int
    {
        $billed = 0;

        DuesCharge::query()->live()->applicableTo($school)
            ->where('year', '>=', now()->year)
            ->each(function (DuesCharge $charge) use ($school, &$billed) {
                $due = MembershipDue::withoutGlobalScopes()->firstOrCreate(
                    ['school_id' => $school->getKey(), 'dues_charge_id' => $charge->getKey()],
                    ['amount_kobo' => $charge->amount_kobo, 'due_date' => $charge->due_date, 'status' => DueStatus::Unpaid],
                );

                $billed += (int) $due->wasRecentlyCreated;
            });

        return $billed;
    }

    /**
     * Withdraw a charge. Unpaid dues are voided; money already paid stays paid.
     *
     * @return int Dues voided.
     */
    public function cancel(DuesCharge $charge): int
    {
        if ($charge->isCancelled()) {
            return 0;
        }

        return DB::transaction(function () use ($charge) {
            $charge->update(['cancelled_at' => now()]);

            $voided = 0;
            MembershipDue::withoutGlobalScopes()
                ->where('dues_charge_id', $charge->getKey())
                ->unpaid()
                ->each(function (MembershipDue $due) use (&$voided) {
                    $due->update(['status' => DueStatus::Void]);
                    $voided++;
                });

            $this->compliance->refresh(fn (Builder $q) => $q->whereIn('id', $charge->schoolsQuery()->select('id')));

            return $voided;
        });
    }

    /**
     * Open (or reuse) an online checkout for some of a school's unpaid dues.
     *
     * @param  Collection<int, MembershipDue>  $dues
     */
    public function startOnlinePayment(School $school, User $payer, Collection $dues, string $returnUrl): GatewayTransaction
    {
        $ids = $dues->map(fn (MembershipDue $due) => $due->getKey())->all();
        sort($ids);
        $amount = (int) $dues->sum('amount_kobo');

        // A second tap on "Pay" within half an hour returns to the same checkout
        // instead of opening a second one that could be paid twice.
        $existing = GatewayTransaction::query()
            ->where('purpose', self::PURPOSE)
            ->where('school_id', $school->getKey())
            ->where('payer_id', $payer->getKey())
            ->where('status', GatewayStatus::Pending)
            ->where('amount_kobo', $amount)
            ->whereNotNull('authorization_url')
            ->where('created_at', '>=', now()->subMinutes(30))
            ->latest('id')
            ->get()
            ->first(fn (GatewayTransaction $t) => $t->meta('due_ids') === $ids);

        return $existing ?? $this->payments->start(self::PURPOSE, $amount, $payer, $school->getKey(), $returnUrl, [
            'due_ids' => $ids,
            'school_name' => $school->name,
        ]);
    }

    /**
     * Record money received for some of a school's dues: marks them paid,
     * issues the receipt number, writes the split ledger and recomputes the
     * school's status. Dues already paid by another route are skipped and
     * any excess is noted on the payment for refund.
     *
     * @param  Collection<int, MembershipDue>  $dues
     */
    public function recordPayment(
        School $school,
        Collection $dues,
        PaymentMethod $method,
        int $amountKobo,
        CarbonInterface $paidAt,
        ?string $reference = null,
        ?GatewayTransaction $transaction = null,
        ?User $paidBy = null,
        ?User $recordedBy = null,
        ?string $notes = null,
    ): DuesPayment {
        $payment = DB::transaction(function () use ($school, $dues, $method, $amountKobo, $paidAt, $reference, $transaction, $paidBy, $recordedBy, $notes) {
            $payable = MembershipDue::withoutGlobalScopes()
                ->whereKey($dues->map(fn (MembershipDue $due) => $due->getKey())->all())
                ->where('school_id', $school->getKey())
                ->unpaid()
                ->lockForUpdate()
                ->with('charge')
                ->get();

            $expected = (int) $payable->sum('amount_kobo');

            if ($amountKobo > $expected) {
                $notes = trim(($notes ?? '')."\n".sprintf(
                    'Received %s against %s due: %s to refund or credit.',
                    Money::format($amountKobo),
                    Money::format($expected),
                    Money::format($amountKobo - $expected),
                ));
            }

            $payment = DuesPayment::withoutGlobalScopes()->create([
                'school_id' => $school->getKey(),
                'amount_kobo' => $amountKobo,
                'method' => $method,
                'gateway_transaction_id' => $transaction?->getKey(),
                'reference' => $reference,
                'paid_at' => $paidAt,
                'paid_by' => $paidBy?->getKey(),
                'recorded_by' => $recordedBy?->getKey(),
                'notes' => $notes ?: null,
            ]);

            $payment->update(['receipt_number' => sprintf('DUES-%d-%06d', $paidAt->year, $payment->getKey())]);

            foreach ($payable as $due) {
                $due->update(['status' => DueStatus::Paid, 'paid_at' => $paidAt, 'dues_payment_id' => $payment->getKey()]);
                $this->allocate($payment, $due, $school);
            }

            $this->compliance->refreshSchool($school);

            return $payment;
        });

        DB::afterCommit(fn () => Notification::send(
            User::query()->holdingRole(Role::Proprietor, ScopeLevel::School, $school->getKey())->get(),
            new DuesPaymentReceived($payment, $school),
        ));

        return $payment;
    }

    /**
     * Split ledger entries for one paid due. Shares are percentages set on
     * the charge; rounding kobo go to the level that set the charge.
     */
    private function allocate(DuesPayment $payment, MembershipDue $due, School $school): void
    {
        $charge = $due->charge;
        $owner = $charge->scope_type->value;
        $shares = $charge->shares() ?: [$owner => 100];

        $amounts = [];
        foreach ($shares as $level => $percent) {
            $amounts[$level] = intdiv($due->amount_kobo * $percent, 100);
        }
        $amounts[$owner] = ($amounts[$owner] ?? 0) + $due->amount_kobo - array_sum($amounts);

        foreach ($amounts as $level => $amount) {
            if ($amount <= 0) {
                continue;
            }

            $level = ScopeLevel::from($level);

            DuesAllocation::query()->create([
                'dues_payment_id' => $payment->getKey(),
                'membership_due_id' => $due->getKey(),
                'level' => $level,
                'state_id' => in_array($level, [ScopeLevel::State, ScopeLevel::Chapter], true) ? $school->state_id : null,
                'chapter_id' => $level === ScopeLevel::Chapter ? $school->chapter_id : null,
                'amount_kobo' => $amount,
            ]);
        }
    }
}
