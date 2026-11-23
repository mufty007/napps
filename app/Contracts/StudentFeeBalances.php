<?php

namespace App\Contracts;

/**
 * What a pupil's family still owes the school, in kobo.
 *
 * Records asks this for transfer clearance and the debtor check, so it never
 * reads another module's tables. The Records module binds a default that uses
 * the figure kept on the student record; the Fees module rebinds it to sum
 * unpaid invoices once those exist.
 */
interface StudentFeeBalances
{
    public function outstandingKobo(int $studentId): int;
}
