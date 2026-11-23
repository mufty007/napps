<?php

namespace Modules\Membership\Seeders;

use App\Enums\GatewayStatus;
use App\Enums\ScopeLevel;
use App\Models\Chapter;
use App\Models\GatewayTransaction;
use App\Models\School;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Modules\Membership\Enums\ApplicationStatus;
use Modules\Membership\Enums\DuesKind;
use Modules\Membership\Enums\PaymentMethod;
use Modules\Membership\Models\DuesCharge;
use Modules\Membership\Models\MembershipApplication;
use Modules\Membership\Models\MembershipDue;
use Modules\Membership\Services\ComplianceService;
use Modules\Membership\Services\DuesLedger;
use Modules\Membership\Services\MembershipNumbers;

/**
 * Local demo for Phase 1, on top of DemoSeeder's Kano and Katsina North data:
 * membership numbers, applications at each stage, this year's dues with a
 * split, payments online and offline, and one suspended school.
 */
class MembershipDemoSeeder extends Seeder
{
    public function run(MembershipNumbers $numbers, DuesLedger $ledger, ComplianceService $compliance): void
    {
        $school = fn (string $name) => School::withoutGlobalScopes()->where('name', $name)->firstOrFail();
        $user = fn (string $phone) => User::query()->where('phone', $phone)->firstOrFail();
        $year = (int) now()->year;

        $kano = Chapter::query()->where('name', 'Kano Chapter')->firstOrFail();
        $katsinaNorth = Chapter::query()->where('name', 'Katsina North Chapter')->firstOrFail();

        $national = $user('+2348000000001');
        $kanoState = $user('+2348000000002');
        $kanoChapter = $user('+2348000000003');
        $bashir = $user('+2348000000004');
        $katsinaChapter = $user('+2348000000011');

        // Membership numbers for every approved demo school.
        School::withoutGlobalScopes()->whereIn('chapter_id', [$kano->id, $katsinaNorth->id])
            ->where('membership_status', '!=', 'pending')
            ->orderBy('id')
            ->each(fn (School $s) => $numbers->assign($s));

        // Approved applications (member since earlier this year).
        foreach ([
            ['Al-Ihsan Model Academy', $bashir, '-10 months', $kanoChapter, $kanoState],
            ['Kano Capital International School', $bashir, '-8 months', $kanoChapter, $kanoState],
            ['Daura Model International School', $user('+2348000000014'), '-6 months', $katsinaChapter, $user('+2348000000010')],
        ] as [$name, $applicant, $ago, $verifier, $approver]) {
            $at = CarbonImmutable::parse($ago);
            MembershipApplication::withoutGlobalScopes()->firstOrCreate(['school_id' => $school($name)->id], [
                'applicant_id' => $applicant->id,
                'status' => ApplicationStatus::Approved,
                'submitted_at' => $at->subDays(9),
                'verified_by' => $verifier->id,
                'verified_at' => $at->subDays(5),
                'approved_by' => $approver->id,
                'approved_at' => $at,
            ]);
        }

        // Waiting for Kano Chapter to verify.
        $this->pendingApplication($school('Gwale Excellence Nursery & Primary School'), $user('+2348000000012'), ApplicationStatus::Submitted, null);

        // Verified by Katsina North, waiting for Katsina State to approve.
        $this->pendingApplication($school('Zango Bright Stars Schools'), $user('+2348000000013'), ApplicationStatus::Verified, $katsinaChapter);

        // This year's dues: national (split), a Kano State levy, a Katsina North levy, next year's draft.
        $charges = [
            [ScopeLevel::National, null, DuesKind::Annual, "Annual dues {$year}", 2_500_000, "{$year}-03-31", [40, 30, 30], $national],
            [ScopeLevel::State, $kano->state_id, DuesKind::Levy, "Kano State development levy {$year}", 500_000, "{$year}-06-30", [0, 100, 0], $kanoState],
            [ScopeLevel::Chapter, $katsinaNorth->id, DuesKind::Levy, 'Katsina North secretariat levy', 300_000, "{$year}-12-31", [0, 0, 100], $katsinaChapter],
        ];

        foreach ($charges as [$level, $scopeId, $kind, $title, $kobo, $due, [$n, $s, $c], $by]) {
            $charge = DuesCharge::query()->firstOrCreate(['title' => $title, 'scope_type' => $level, 'scope_id' => $scopeId], [
                'kind' => $kind, 'year' => $year, 'amount_kobo' => $kobo, 'due_date' => $due,
                'share_national' => $n, 'share_state' => $s, 'share_chapter' => $c,
                'created_by' => $by->id, 'issued_by' => $by->id, 'issued_at' => now()->subMonths(9),
                'description' => $kind === DuesKind::Annual ? 'Covers national, state and chapter running costs and the member platform.' : null,
            ]);
            $ledger->bill($charge);
        }

        DuesCharge::query()->firstOrCreate(['title' => 'Annual dues '.($year + 1), 'scope_type' => ScopeLevel::National], [
            'kind' => DuesKind::Annual, 'year' => $year + 1, 'amount_kobo' => 2_750_000, 'due_date' => ($year + 1).'-03-31',
            'share_national' => 40, 'share_state' => 30, 'share_chapter' => 30, 'created_by' => $national->id,
            'description' => 'Draft for the national executive to review before issuing.',
        ]);

        // Payments: online, cash and bank transfer.
        $this->pay($ledger, $school('Al-Ihsan Model Academy'), PaymentMethod::Online, '-7 months', paidBy: $bashir, reference: 'NAPPS-DEMO-ONLINE-1');
        $this->pay($ledger, $school('Tarauni Heritage Academy'), PaymentMethod::Cash, '-5 months', recordedBy: $kanoChapter);
        $this->pay($ledger, $school('Daura Model International School'), PaymentMethod::BankTransfer, '-4 months', recordedBy: $katsinaChapter, reference: 'TLR-DAURA-0457', onlyOverdue: true);
        $this->pay($ledger, $school('Mai\'adua Pinnacle Academy'), PaymentMethod::Online, '-3 months', paidBy: $user('+2348000000014'), reference: 'NAPPS-DEMO-ONLINE-2');
        $this->pay($ledger, $school('Sabon Gari Unity College'), PaymentMethod::Cash, '-10 months', recordedBy: $kanoChapter);

        // Gateway rows so payment history / reconciliation screens look real.
        $this->seedGatewayPayments([
            [$school('Al-Ihsan Model Academy'), $bashir, 'NAPPS-DEMO-ONLINE-1', 2_500_000, '-7 months'],
            [$school('Kano Capital International School'), $bashir, 'NAPPS-DEMO-ONLINE-KC-1', 500_000, '-2 months'],
            [$school('Daura Model International School'), $user('+2348000000014'), 'NAPPS-DEMO-ONLINE-DAURA-1', 300_000, '-20 days'],
        ]);

        $compliance->refresh();

        $sabonGari = $school('Sabon Gari Unity College');
        if (! $compliance->isSuspended($sabonGari)) {
            $compliance->suspend($sabonGari, $kanoState, 'Dues unpaid since March and no reply to two chapter letters');
        }

        $this->seedNotifications([
            $national,
            $kanoState,
            $kanoChapter,
            $bashir,
            $user('+2348000000005'),
            $user('+2348000000008'),
            $user('+2348000000011'),
        ]);
    }

    /**
     * @param  list<array{0: School, 1: User, 2: string, 3: int, 4: string}>  $rows
     */
    private function seedGatewayPayments(array $rows): void
    {
        foreach ($rows as [$school, $payer, $reference, $amount, $when]) {
            $paidAt = CarbonImmutable::parse($when);
            GatewayTransaction::query()->updateOrCreate(
                ['reference' => $reference],
                [
                    'gateway' => 'fake',
                    'purpose' => 'membership.dues',
                    'school_id' => $school->id,
                    'payer_id' => $payer->id,
                    'amount_kobo' => $amount,
                    'currency' => 'NGN',
                    'status' => GatewayStatus::Success,
                    'channel' => 'card',
                    'authorization_url' => null,
                    'metadata' => ['demo' => true, 'school' => $school->name],
                    'gateway_payload' => ['status' => 'success', 'reference' => $reference],
                    'paid_at' => $paidAt,
                    'verified_at' => $paidAt->addMinutes(2),
                    'created_at' => $paidAt,
                    'updated_at' => $paidAt,
                ],
            );
        }
    }

    /** @param  list<User>  $users */
    private function seedNotifications(array $users): void
    {
        $samples = [
            ['demo.membership.dues', 'Dues payment received', 'A dues payment was recorded for your school.'],
            ['demo.membership.application', 'Application update', 'A membership application in your chapter needs attention.'],
            ['demo.claims.assigned', 'Claim assigned', 'A debt claim file was sent to the legal panel.'],
            ['demo.announcements.event', 'Event reminder', 'Upcoming webinar: Fee collection for chapter schools.'],
        ];

        foreach ($users as $user) {
            foreach ($samples as [$type, $title, $body]) {
                $exists = DatabaseNotification::query()
                    ->where('notifiable_type', $user->getMorphClass())
                    ->where('notifiable_id', $user->id)
                    ->where('type', $type)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DatabaseNotification::query()->create([
                    'id' => (string) Str::uuid(),
                    'type' => $type,
                    'notifiable_type' => $user->getMorphClass(),
                    'notifiable_id' => $user->id,
                    'data' => ['title' => $title, 'body' => $body, 'demo' => true],
                    'read_at' => random_int(0, 1) ? now()->subDays(random_int(1, 5)) : null,
                    'created_at' => now()->subDays(random_int(0, 10)),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function pendingApplication(School $school, User $applicant, ApplicationStatus $status, ?User $verifier): void
    {
        $application = MembershipApplication::withoutGlobalScopes()->firstOrCreate(['school_id' => $school->id], [
            'applicant_id' => $applicant->id,
            'status' => $status,
            'submitted_at' => now()->subDays($status === ApplicationStatus::Submitted ? 2 : 6),
            'verified_by' => $verifier?->id,
            'verified_at' => $verifier ? now()->subDays(1) : null,
            'verification_note' => $verifier ? 'Visited the school; CAC certificate matches and premises are as described.' : null,
        ]);

        if (! $application->wasRecentlyCreated) {
            return;
        }

        foreach (['cac_certificate' => 'CAC certificate of incorporation', 'ministry_approval' => 'State ministry approval letter'] as $collection => $title) {
            $pdf = Pdf::loadHTML('<h1 style="font-family: DejaVu Sans">'.e($title).'</h1><p style="font-family: DejaVu Sans">'.e($school->name).' · '.e((string) $school->registration_number).'</p><p style="font-family: DejaVu Sans; color: #888">Demo document</p>')->output();

            $application->addMediaFromString($pdf)->usingFileName($collection.'.pdf')->toMediaCollection($collection);
        }
    }

    private function pay(
        DuesLedger $ledger,
        School $school,
        PaymentMethod $method,
        string $when,
        ?User $paidBy = null,
        ?User $recordedBy = null,
        ?string $reference = null,
        bool $onlyOverdue = false,
    ): void {
        $dues = MembershipDue::withoutGlobalScopes()->where('school_id', $school->id)->unpaid()
            ->when($onlyOverdue, fn ($q) => $q->overdue())
            ->get();

        if ($dues->isEmpty()) {
            return;
        }

        $ledger->recordPayment(
            school: $school,
            dues: $dues,
            method: $method,
            amountKobo: (int) $dues->sum('amount_kobo'),
            paidAt: CarbonImmutable::parse($when),
            reference: $reference,
            paidBy: $paidBy,
            recordedBy: $recordedBy,
        );
    }
}
