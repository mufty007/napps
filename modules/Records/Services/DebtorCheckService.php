<?php

namespace Modules\Records\Services;

use App\Contracts\StudentFeeBalances;
use App\Models\Scopes\SchoolOwnedScope;
use App\Models\User;
use App\Support\PhoneNumber;
use DomainException;
use Modules\Records\Models\Student;

/**
 * The cross-school debtor check (REC-2). A school asks, by guardian phone,
 * whether that family owes fees at other member schools.
 *
 * Privacy (NDPA) is the point of this class:
 *  - only pupils whose guardian consented at enrolment are ever looked at;
 *  - the answer is "clear" or "outstanding" plus a total, never which school,
 *    which child or which invoices;
 *  - the asking school's own pupils are left out (it already sees them);
 *  - every check is written to the audit log, on the asker's side and on each
 *    pupil record that was matched, so a guardian's access request can show it.
 */
class DebtorCheckService
{
    public const CLEAR = 'clear';

    public const OUTSTANDING = 'outstanding';

    public function __construct(private readonly StudentFeeBalances $balances) {}

    /**
     * @param  list<int>  $askingSchoolIds  schools the asker belongs to (left out of the answer)
     * @return array{status: string, amount_kobo: int}
     *
     * @throws DomainException
     */
    public function check(User $by, string $guardianPhone, array $askingSchoolIds = []): array
    {
        $phone = PhoneNumber::normalize($guardianPhone);

        if ($phone === null) {
            throw new DomainException(__('Enter a valid Nigerian phone number.'));
        }

        $matched = Student::query()
            ->withoutGlobalScope(SchoolOwnedScope::class)
            ->where('guardian_phone', $phone)
            ->where('debtor_check_consent', true)
            ->when($askingSchoolIds !== [], fn ($q) => $q->whereNotIn('school_id', $askingSchoolIds))
            ->get();

        $total = (int) $matched->sum(fn (Student $student) => $this->balances->outstandingKobo($student->getKey()));
        $status = $total > 0 ? self::OUTSTANDING : self::CLEAR;

        $result = ['status' => $status, 'amount_kobo' => $total];

        // The asker's trail: the phone is masked, the matches are only counted.
        activity('records')
            ->causedBy($by)
            ->event('debtor-check')
            ->withProperties([
                'guardian_phone' => PhoneNumber::mask($phone),
                'asking_school_ids' => $askingSchoolIds,
                'result' => $status,
                'amount_kobo' => $total,
                'records_matched' => $matched->count(),
            ])
            ->log('Ran a debtor check');

        // The data subject's trail: each pupil record that fed the answer.
        foreach ($matched as $student) {
            activity('records')
                ->performedOn($student)
                ->causedBy($by)
                ->event('debtor-check-viewed')
                ->withProperties(['asking_school_ids' => $askingSchoolIds, 'result' => $status])
                ->log('Debtor check read this pupil\'s fee standing');
        }

        return $result;
    }
}
