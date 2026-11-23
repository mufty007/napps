<?php

namespace Modules\Claims\Seeders;

use App\Models\School;
use App\Models\User;
use App\Support\Money;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Modules\Claims\Enums\ClaimEventType;
use Modules\Claims\Enums\ClaimStage;
use Modules\Claims\Models\ClaimCase;
use Modules\Claims\Models\ClaimEvent;
use Modules\Claims\Models\StateClaimSetting;

class ClaimsDemoSeeder extends Seeder
{
    public function run(): void
    {
        $lawyer = User::query()->where('phone', '+2348000000008')->first();
        $bashir = User::query()->where('phone', '+2348000000004')->first();
        $nasiru = User::query()->where('phone', '+2348000000014')->first();

        if (! $lawyer || ! $bashir) {
            return;
        }

        $school = fn (string $name) => School::withoutGlobalScopes()->where('name', $name)->firstOrFail();

        $rows = [
            [
                'reference' => 'CLM-KN-2026-001',
                'school' => 'Kano Capital International School',
                'opened_by' => $bashir,
                'debtor_name' => 'Mallam Abdullahi Sule',
                'debtor_phone' => '+2348032001001',
                'amount_kobo' => 485_000_00,
                'days_overdue' => 128,
                'stage' => ClaimStage::Mediation,
                'summary' => "Term 1 and Term 2 fees unpaid after withdrawal mid-term.\nClaim pack includes signed fee agreement, invoices, reminder log and draft demand letter.",
                'notes' => 'Chapter mediation scheduled for next Thursday at the Kano chapter secretariat, Zaria Road.',
                'escalated_at' => now()->subDays(18),
            ],
            [
                'reference' => 'CLM-KN-2026-002',
                'school' => 'Al-Ihsan Model Academy',
                'opened_by' => $bashir,
                'debtor_name' => 'Mrs. Grace Okonkwo',
                'debtor_phone' => '+2348024119002',
                'amount_kobo' => 312_500_00,
                'days_overdue' => 102,
                'stage' => ClaimStage::DemandSent,
                'summary' => 'Sibling discount withdrawn when one child left; balance on second child unpaid for 102 days.',
                'notes' => null,
                'escalated_at' => now()->subDays(6),
            ],
            [
                'reference' => 'CLM-KT-2026-003',
                'school' => 'Daura Model International School',
                'opened_by' => $nasiru ?? $bashir,
                'debtor_name' => 'Alhaji Musa Dankano',
                'debtor_phone' => '+2348067003303',
                'amount_kobo' => 760_000_00,
                'days_overdue' => 145,
                'stage' => ClaimStage::Filed,
                'court_name' => 'Daura Magistrate Court',
                'court_file_number' => 'SC/DAU/44/2026',
                'summary' => 'Full year fees plus lunch. Demand ignored. Filed in small claims.',
                'notes' => 'Hearing date requested. Supporting affidavits prepared.',
                'escalated_at' => now()->subDays(40),
            ],
            [
                'reference' => 'CLM-KN-2025-014',
                'school' => 'Tarauni Heritage Academy',
                'opened_by' => $bashir,
                'debtor_name' => 'Mr. Chinedu Eze',
                'debtor_phone' => '+2348091004404',
                'amount_kobo' => 198_000_00,
                'days_overdue' => 210,
                'stage' => ClaimStage::Closed,
                'summary' => 'Settled at chapter mediation after judgment threat. Parent paid in three instalments.',
                'notes' => 'Closed — full recovery via mediation. No court fees charged.',
                'escalated_at' => now()->subMonths(5),
                'closed_at' => now()->subMonths(2),
            ],
        ];

        // Claims are on in every state that has a demo claim, confirmed by the demo barrister.
        $secretariat = User::query()->where('phone', '+2348000000001')->first() ?? $bashir;

        collect($rows)
            ->map(fn (array $row) => $school($row['school'])->state_id)
            ->unique()
            ->each(fn (int $stateId) => StateClaimSetting::query()->updateOrCreate(['state_id' => $stateId], [
                'enabled' => true,
                'barrister_confirmed_at' => now()->subMonths(6),
                'notes' => 'Demo: confirmed by Barr. Aisha Bello, Bello & Co. Chambers.',
                'updated_by' => $secretariat->id,
            ]));

        foreach ($rows as $row) {
            $case = ClaimCase::query()->updateOrCreate(
                ['reference' => $row['reference']],
                [
                    'school_id' => $school($row['school'])->id,
                    'opened_by' => $row['opened_by']->id,
                    'lawyer_id' => $lawyer->id,
                    'debtor_name' => $row['debtor_name'],
                    'debtor_phone' => $row['debtor_phone'],
                    'amount_kobo' => $row['amount_kobo'],
                    'days_overdue' => $row['days_overdue'],
                    'stage' => $row['stage'],
                    'court_name' => $row['court_name'] ?? null,
                    'court_file_number' => $row['court_file_number'] ?? null,
                    'summary' => $row['summary'],
                    'notes' => $row['notes'],
                    'escalated_at' => $row['escalated_at'],
                    'closed_at' => $row['closed_at'] ?? null,
                ],
            );

            $this->seedHistory($case, $row['opened_by'], $lawyer, $secretariat);
        }
    }

    /**
     * Timeline, hearings, money and a demand letter for one demo case.
     * Written once: re-running the seeder leaves existing histories alone.
     */
    private function seedHistory(ClaimCase $case, User $opener, User $lawyer, User $secretariat): void
    {
        if ($case->events()->exists()) {
            return;
        }

        $start = $case->escalated_at ?? now()->subDays(10);
        $end = $case->closed_at ?? now();
        $stages = array_slice(ClaimStage::pipeline(), 0, (int) array_search($case->stage, ClaimStage::pipeline(), true) + 1);

        $event = fn (ClaimEventType $type, ?User $by, ?string $body, ?array $meta, CarbonInterface $at) => ClaimEvent::query()->create([
            'claim_case_id' => $case->id,
            'user_id' => $by?->id,
            'type' => $type,
            'body' => $body,
            'meta' => $meta,
            'created_at' => $at,
        ]);

        $event(ClaimEventType::StageChange, $opener, __('Claim opened by :school.', ['school' => $case->school->name]), [
            'from' => null, 'to' => ClaimStage::DemandSent->value, 'amount_kobo' => $case->amount_kobo,
        ], $start);
        $event(ClaimEventType::Assignment, $secretariat, __(':name was assigned to this claim.', ['name' => $lawyer->name]), [
            'lawyer_id' => $lawyer->id, 'previous_lawyer_id' => null,
        ], $start->copy()->addHours(5));

        // One stage_change per step after the first, spread evenly up to now (or closing).
        $steps = count($stages) - 1;
        foreach (array_slice($stages, 1) as $i => $stage) {
            $at = $start->copy()->addSeconds((int) round($start->diffInSeconds($end) * ($i + 1) / ($steps + 1)));
            $event(ClaimEventType::StageChange, $lawyer, null, ['from' => $stages[$i]->value, 'to' => $stage->value], $at);
        }

        if ($case->notes) {
            $event(ClaimEventType::Note, $lawyer, $case->notes, null, $end->copy()->subHours(3));
        }

        if ($case->court_name) {
            $event(ClaimEventType::Filing, $lawyer, "{$case->court_name} · File no. {$case->court_file_number}", [
                'court_name' => $case->court_name, 'court_file_number' => $case->court_file_number,
            ], $start->copy()->addDays(20));
        }

        match ($case->reference) {
            'CLM-KN-2026-001' => $this->hearing($case, $lawyer, Carbon::now()->next(Carbon::THURSDAY)->setTime(11, 0), 'Kano chapter secretariat, Zaria Road', null, $event),
            'CLM-KT-2026-003' => $this->seedCourtActivity($case, $lawyer, $event),
            'CLM-KN-2025-014' => $this->seedSettlement($case, $lawyer, $event),
            default => null,
        };

        $this->demandLetter($case, $opener);
    }

    private function seedCourtActivity(ClaimCase $case, User $lawyer, \Closure $event): void
    {
        $this->hearing($case, $lawyer, now()->subDays(12)->setTime(10, 0), 'Daura Magistrate Court', 'Defendant absent. Adjourned for service of hearing notice.', $event);
        $this->hearing($case, $lawyer, now()->addDays(2)->setTime(10, 0), 'Daura Magistrate Court', null, $event);

        foreach ([['Filing fee, small claims registry', 5_000_00], ['Process server', 3_500_00]] as [$description, $kobo]) {
            $case->fees()->create(['description' => $description, 'amount_kobo' => $kobo, 'recorded_by' => $lawyer->id]);
            $event(ClaimEventType::Payment, $lawyer, "{$description}: ".Money::format($kobo), ['amount_kobo' => $kobo], now()->subDays(25));
        }
    }

    private function seedSettlement(ClaimCase $case, User $lawyer, \Closure $event): void
    {
        $paidAt = $case->closed_at ?? now()->subMonths(2);

        $settlement = $case->settlements()->create([
            'amount_kobo' => $case->amount_kobo,
            'terms' => 'Paid in three monthly instalments after chapter mediation. No court fees charged.',
            'paid_at' => $paidAt,
            'recorded_by' => $lawyer->id,
        ]);

        $event(ClaimEventType::Settlement, $lawyer, __(':amount settled and paid on :date.', [
            'amount' => Money::format($case->amount_kobo),
            'date' => $paidAt->format('j M Y'),
        ]), ['settlement_id' => $settlement->id, 'amount_kobo' => $case->amount_kobo, 'paid' => true], $paidAt->copy()->subDay());
    }

    private function hearing(ClaimCase $case, User $lawyer, CarbonInterface $at, string $venue, ?string $outcome, \Closure $event): void
    {
        $hearing = $case->hearings()->create([
            'scheduled_at' => $at,
            'venue' => $venue,
            'outcome' => $outcome,
            'created_by' => $lawyer->id,
        ]);

        $event(ClaimEventType::Hearing, $lawyer, __('Hearing set for :when at :venue.', [
            'when' => $at->format('j M Y, g:ia'),
            'venue' => $venue,
        ]), ['hearing_id' => $hearing->id], $at->copy()->subDays(7)->min(now()));
    }

    /** Attach a filled demand letter when the Templates module and its library are present. */
    private function demandLetter(ClaimCase $case, User $by): void
    {
        $service = 'Modules\\Templates\\Services\\TemplateService';

        if (! class_exists($service) || $case->getFirstMedia('demand_letter') !== null) {
            return;
        }

        try {
            $pdf = app($service)->render('demand_letter', [
                'school_name' => $case->school->name,
                'school_address' => $case->school->address,
                'date' => ($case->escalated_at ?? now())->toDateString(),
                'debtor_name' => $case->debtor_name,
                'amount' => Money::toNaira($case->amount_kobo),
                'due_date' => ($case->escalated_at ?? now())->copy()->addDays(14)->toDateString(),
                'signatory_name' => $by->name,
                'signatory_title' => 'Proprietor',
            ]);

            $case->addMediaFromString($pdf)
                ->usingName('Demand letter '.$case->reference)
                ->usingFileName('demand-letter-'.strtolower($case->reference).'.pdf')
                ->toMediaCollection('demand_letter');
        } catch (\Throwable) {
            // Demo data only: the template library may not be seeded yet.
        }
    }
}
