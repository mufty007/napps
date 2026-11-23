<?php

namespace Modules\Records\Support;

use App\Contracts\StudentFeeBalances;
use App\Models\Scopes\SchoolOwnedScope;
use Modules\Records\Models\Student;

/**
 * Default balance source: the outstanding figure kept on the student record.
 * Used until the Fees module binds its own StudentFeeBalances from unpaid invoices.
 */
class RecordedBalances implements StudentFeeBalances
{
    public function outstandingKobo(int $studentId): int
    {
        return (int) Student::query()
            ->withoutGlobalScope(SchoolOwnedScope::class)
            ->whereKey($studentId)
            ->value('outstanding_kobo');
    }
}
