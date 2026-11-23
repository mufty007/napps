<?php

namespace Modules\Fees\Seeders;

use App\Models\School;
use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\Fees\Enums\FeePaymentMethod;
use Modules\Fees\Enums\InvoiceStatus;
use Modules\Fees\Enums\ReminderKind;
use Modules\Fees\Models\FeeInvoice;
use Modules\Fees\Models\FeePayment;
use Modules\Fees\Models\FeeReminder;
use Modules\Fees\Models\FeeStructure;

/**
 * Local-only sample fees for Al-Ihsan Model Academy: two fee structures and
 * a spread of invoices that fills every ageing bucket, a part payment, an
 * invoice already escalated to a claim, and two children of the demo parent
 * (Aisha Garba) so the parent portal has something to show.
 */
class FeesDemoSeeder extends Seeder
{
    public function run(): void
    {
        $school = School::withoutGlobalScopes()->where('name', 'Al-Ihsan Model Academy')->first();

        if (! $school) {
            return;
        }

        $parent = User::query()->where('phone', '+2348000000007')->first();
        $bursar = User::query()->where('phone', '+2348000000005')->first();

        $primary = $this->structure($school, 'Primary 1–3', 'First term', [
            ['Tuition', 120_000_00],
            ['Textbooks', 25_000_00],
            ['Uniform', 18_000_00],
            ['ICT and library', 7_000_00],
        ]);

        $nursery = $this->structure($school, 'Nursery', 'First term', [
            ['Tuition', 90_000_00],
            ['Activity and snacks', 10_000_00],
        ]);

        // [ref, student, structure, guardian name, guardian phone, guardian user, due offset (days), paid kobo, status override]
        $rows = [
            ['FEE-DEMO-001', 'Amina Garba', $primary, 'Aisha Garba', '+2348000000007', $parent, 10, 0, null],
            ['FEE-DEMO-002', 'Yusuf Garba', $nursery, 'Aisha Garba', '+2348000000007', $parent, -30, 100_000_00, null],
            ['FEE-DEMO-003', 'Zainab Musa', $primary, 'Mal. Musa Danjuma', '+2348057654321', null, 5, 0, null],
            ['FEE-DEMO-004', 'Ibrahim Danladi', $primary, 'Mrs. Hauwa Danladi', '+2348031230004', null, -12, 0, null],
            ['FEE-DEMO-005', 'Maryam Sule', $primary, 'Alhaji Sule Kabir', '+2348031230005', null, -40, 60_000_00, null],
            ['FEE-DEMO-006', 'Hassan Abubakar', $primary, 'Mrs. Rakiya Abubakar', '+2348031230006', null, -75, 0, null],
            ['FEE-DEMO-007', 'Fatima Okoro', $primary, 'Mr. Emeka Okoro', '+2348031230007', null, -120, 40_000_00, null],
            ['FEE-DEMO-008', 'Khadija Bello', $nursery, 'Hajiya Bello', '+2348031230008', null, -20, 100_000_00, null],
            ['FEE-DEMO-009', 'Musa Yakubu', $nursery, 'Mal. Yakubu Isa', '+2348031230009', null, 14, 0, InvoiceStatus::Draft],
        ];

        foreach ($rows as [$reference, $student, $structure, $guardianName, $phone, $guardian, $offset, $paid, $override]) {
            $total = (int) $structure->items->sum('amount_kobo');
            $due = today()->addDays($offset);

            $invoice = FeeInvoice::withoutGlobalScopes()->firstOrCreate(['reference' => $reference], [
                'school_id' => $school->getKey(),
                'student_name' => $student,
                'guardian_user_id' => $guardian?->getKey(),
                'guardian_name' => $guardianName,
                'guardian_phone' => $phone,
                'fee_structure_id' => $structure->getKey(),
                'lines' => $structure->items->map(fn ($i) => ['name' => $i->name, 'amount_kobo' => $i->amount_kobo])->values()->all(),
                'total_kobo' => $total,
                'paid_kobo' => $paid,
                'due_date' => $due,
                'status' => $override ?? $this->statusFor($total, $paid, $offset),
                'issued_at' => $override === InvoiceStatus::Draft ? null : now()->subDays(max(1, 30 - $offset)),
            ]);

            if ($invoice->wasRecentlyCreated) {
                $this->payAndRemind($invoice, $paid, $offset, $bursar);
            }
        }

        // An invoice 102 days overdue that the school has already escalated (see ClaimsDemoSeeder).
        $escalated = FeeInvoice::withoutGlobalScopes()->firstOrCreate(['reference' => 'FEE-DEMO-010'], [
            'school_id' => $school->getKey(),
            'student_name' => 'Daniel Okonkwo',
            'guardian_name' => 'Mrs. Grace Okonkwo',
            'guardian_phone' => '+2348024119002',
            'lines' => [['name' => 'Tuition and levies, second child', 'amount_kobo' => 312_500_00]],
            'total_kobo' => 312_500_00,
            'paid_kobo' => 0,
            'due_date' => today()->subDays(102),
            'status' => InvoiceStatus::Overdue,
            'issued_at' => now()->subDays(140),
            'claim_reference' => 'CLM-KN-2026-002',
            'escalated_at' => now()->subDays(6),
        ]);

        if ($escalated->wasRecentlyCreated) {
            $this->payAndRemind($escalated, 0, -102, $bursar);
        }
    }

    /**
     * @param  list<array{0: string, 1: int}>  $items
     */
    private function structure(School $school, string $name, string $term, array $items): FeeStructure
    {
        $structure = FeeStructure::withoutGlobalScopes()->firstOrCreate(
            ['school_id' => $school->getKey(), 'name' => $name, 'academic_year' => '2026/2027', 'term' => $term],
            ['active' => true],
        );

        if ($structure->wasRecentlyCreated) {
            foreach ($items as $i => [$itemName, $kobo]) {
                $structure->items()->create(['name' => $itemName, 'amount_kobo' => $kobo, 'sort' => $i]);
            }
        }

        return $structure->load('items');
    }

    private function statusFor(int $total, int $paid, int $offset): InvoiceStatus
    {
        return match (true) {
            $paid >= $total => InvoiceStatus::Paid,
            $offset < 0 => InvoiceStatus::Overdue,
            $paid > 0 => InvoiceStatus::Partial,
            default => InvoiceStatus::Issued,
        };
    }

    private function payAndRemind(FeeInvoice $invoice, int $paid, int $offset, ?User $bursar): void
    {
        if ($paid > 0) {
            FeePayment::query()->create([
                'fee_invoice_id' => $invoice->getKey(),
                'amount_kobo' => $paid,
                'method' => $offset % 2 === 0 ? FeePaymentMethod::Cash : FeePaymentMethod::Transfer,
                'reference' => 'DEMO-'.$invoice->getKey(),
                'recorded_by' => $bursar?->getKey(),
                'paid_at' => now()->subDays(max(1, abs($offset) - 5)),
            ]);
        }

        // Reminders already sent for the days it has been late.
        $late = -$offset;

        foreach ([[ReminderKind::On, 0], [ReminderKind::Week, 7], [ReminderKind::Fortnight, 14], [ReminderKind::Month, 30]] as [$kind, $days]) {
            if ($late >= $days && $invoice->status !== InvoiceStatus::Paid && $invoice->status !== InvoiceStatus::Draft) {
                FeeReminder::query()->create([
                    'fee_invoice_id' => $invoice->getKey(),
                    'channel' => 'sms',
                    'sent_at' => now()->subDays($late - $days),
                    'kind' => $kind,
                ]);
            }
        }
    }
}
