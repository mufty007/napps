<?php

namespace Modules\Records\Services;

use App\Contracts\StudentFeeBalances;
use App\Models\School;
use App\Models\Scopes\SchoolOwnedScope;
use App\Models\User;
use App\Support\PhoneNumber;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Records\Enums\StudentStatus;
use Modules\Records\Enums\TransferStatus;
use Modules\Records\Models\Student;
use Modules\Records\Models\TransferRequest;

/**
 * Transfer clearance (REC-2): the school a pupil is moving to asks the school
 * that holds the record whether the pupil is cleared. The holder has five
 * working days to answer.
 */
class TransferService
{
    /** Working days the holding school has to reply. */
    public const SLA_WORKING_DAYS = 5;

    public function __construct(private readonly StudentFeeBalances $balances) {}

    /**
     * End of the fifth working day (Monday to Friday) after the request.
     * Public holidays are not modelled.
     */
    public function slaDueAt(?CarbonInterface $from = null): CarbonInterface
    {
        return ($from ?? now())->copy()->addWeekdays(self::SLA_WORKING_DAYS)->endOfDay();
    }

    /**
     * Find an active pupil at another school from name and guardian phone.
     * The guardian's phone acts as the shared secret, so a school cannot
     * browse or guess at other schools' registers.
     *
     * @param  list<int>  $excludeSchoolIds  the asking school(s)
     */
    public function locate(string $firstName, string $lastName, string $guardianPhone, array $excludeSchoolIds = []): ?Student
    {
        $phone = PhoneNumber::normalize($guardianPhone);

        if ($phone === null) {
            return null;
        }

        return Student::query()
            ->withoutGlobalScope(SchoolOwnedScope::class)
            ->where('status', StudentStatus::Active->value)
            ->where('guardian_phone', $phone)
            ->whereRaw('lower(first_name) = ?', [mb_strtolower(trim($firstName))])
            ->whereRaw('lower(last_name) = ?', [mb_strtolower(trim($lastName))])
            ->when($excludeSchoolIds !== [], fn ($q) => $q->whereNotIn('school_id', $excludeSchoolIds))
            ->orderBy('id')
            ->first();
    }

    /**
     * @throws DomainException
     */
    public function request(User $by, Student $student, ?School $to = null): TransferRequest
    {
        if (! $student->isActive()) {
            throw new DomainException(__('This pupil is no longer on the register.'));
        }

        if ($to !== null && $to->getKey() === $student->school_id) {
            throw new DomainException(__('The pupil is already enrolled at your school.'));
        }

        $open = TransferRequest::query()
            ->pending()
            ->where('student_id', $student->getKey())
            ->when($to, fn ($q, School $s) => $q->where('to_school_id', $s->getKey()))
            ->exists();

        if ($open) {
            throw new DomainException(__('A clearance request for this pupil is already waiting for a reply.'));
        }

        return TransferRequest::query()->create([
            'school_id' => $student->school_id,
            'to_school_id' => $to?->getKey(),
            'student_id' => $student->getKey(),
            'requested_by' => $by->getKey(),
            'status' => TransferStatus::Pending,
            'sla_due_at' => $this->slaDueAt(),
        ]);
    }

    /**
     * What the holding school's books say the family owes, to pre-fill the reply.
     */
    public function suggestedOutstanding(TransferRequest $request): int
    {
        return $this->balances->outstandingKobo($request->student_id);
    }

    /**
     * The holding school answers. Clearing marks the pupil as transferred.
     *
     * @throws DomainException
     */
    public function respond(TransferRequest $request, User $by, TransferStatus $decision, ?string $notes, int $outstandingKobo = 0): TransferRequest
    {
        if (! $request->isPending()) {
            throw new DomainException(__('This request has already been answered.'));
        }

        if (! in_array($decision, [TransferStatus::Cleared, TransferStatus::Blocked], true)) {
            throw new DomainException(__('Reply with cleared or blocked.'));
        }

        return DB::transaction(function () use ($request, $by, $decision, $notes, $outstandingKobo) {
            $request->update([
                'status' => $decision,
                'response_notes' => filled($notes) ? trim((string) $notes) : null,
                'responded_by' => $by->getKey(),
                'responded_at' => now(),
                'outstanding_kobo' => $decision === TransferStatus::Cleared ? 0 : max(0, $outstandingKobo),
            ]);

            if ($decision === TransferStatus::Cleared) {
                $student = Student::query()->withoutGlobalScope(SchoolOwnedScope::class)->find($request->student_id);

                $student?->update(['status' => StudentStatus::Transferred, 'exited_at' => today()]);
            }

            return $request;
        });
    }

    /**
     * @throws DomainException
     */
    public function cancel(TransferRequest $request, User $by): TransferRequest
    {
        if (! $request->isPending()) {
            throw new DomainException(__('Only a request that is still waiting can be cancelled.'));
        }

        $request->update([
            'status' => TransferStatus::Cancelled,
            'responded_by' => $by->getKey(),
            'responded_at' => now(),
        ]);

        return $request;
    }
}
