<?php

namespace Modules\Membership\Services;

use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Enums\ScopeLevel;
use App\Models\RoleAssignment;
use App\Models\School;
use App\Models\User;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Modules\Membership\Enums\ApplicationStatus;
use Modules\Membership\Models\MembershipApplication;
use Modules\Membership\Notifications\ApplicationNeedsReview;
use Modules\Membership\Notifications\ApplicationUpdate;

/**
 * Onboarding (PRD): the school applies, the chapter verifies, the state
 * approves, and the school gets a membership number and certificate.
 * With napps.membership.state_approval_required = false the chapter's
 * verification approves directly (MEM-2).
 */
class ApplicationWorkflow
{
    public function __construct(
        private readonly MembershipNumbers $numbers,
        private readonly DuesLedger $ledger,
        private readonly ComplianceService $compliance,
    ) {}

    /**
     * MEM-1: register the school as pending and notify its chapter.
     *
     * @param  array<string, mixed>  $school  Validated school fields.
     * @param  array<string, UploadedFile>  $documents  collection => file
     */
    public function submit(User $applicant, array $school, array $documents): MembershipApplication
    {
        $application = DB::transaction(function () use ($applicant, $school, $documents) {
            $record = School::withoutGlobalScopes()->create([
                ...$school,
                'membership_status' => MembershipStatus::Pending,
            ]);

            $application = MembershipApplication::withoutGlobalScopes()->create([
                'school_id' => $record->getKey(),
                'applicant_id' => $applicant->getKey(),
                'status' => ApplicationStatus::Submitted,
                'submitted_at' => now(),
            ]);

            foreach ($documents as $collection => $file) {
                $application->addMedia($file)->toMediaCollection($collection);
            }

            return $application;
        });

        $this->notifyOfficers($application, Role::ChapterExecutive, ScopeLevel::Chapter, $application->school->chapter_id);
        $applicant->notify(new ApplicationUpdate($application));

        return $application;
    }

    /**
     * Chapter verification. Passes the school to the state, or approves it
     * straight away when state approval is switched off.
     */
    public function verify(MembershipApplication $application, User $by, ?string $note = null): MembershipApplication
    {
        $this->guard($application, ApplicationStatus::Submitted);

        $application->update([
            'status' => ApplicationStatus::Verified,
            'verified_by' => $by->getKey(),
            'verified_at' => now(),
            'verification_note' => $note,
        ]);

        if (! config('napps.membership.state_approval_required', true)) {
            return $this->approve($application, $by);
        }

        $this->notifyOfficers($application, Role::StateExecutive, ScopeLevel::State, $application->school->state_id);
        $application->applicant->notify(new ApplicationUpdate($application));

        return $application;
    }

    /**
     * State approval: membership number, proprietor role, this year's dues,
     * compliance status, and the certificate goes to the applicant.
     */
    public function approve(MembershipApplication $application, User $by): MembershipApplication
    {
        $this->guard($application, ApplicationStatus::Verified);

        DB::transaction(function () use ($application, $by) {
            $school = $application->school;

            $this->numbers->assign($school);
            $school->forceFill(['membership_status' => MembershipStatus::Active])->save();

            $application->update([
                'status' => ApplicationStatus::Approved,
                'approved_by' => $by->getKey(),
                'approved_at' => now(),
            ]);

            RoleAssignment::grant($application->applicant, Role::Proprietor, ScopeLevel::School, $school->getKey(), $by);

            $this->ledger->billNewMember($school);
            $this->compliance->refreshSchool($school);
        });

        $application->applicant->notify(new ApplicationUpdate($application->fresh(['school']) ?? $application));

        return $application;
    }

    public function reject(MembershipApplication $application, User $by, string $reason): MembershipApplication
    {
        if (! $application->status->isOpen()) {
            throw new DomainException(__('This application has already been decided.'));
        }

        DB::transaction(function () use ($application, $by, $reason) {
            $application->update([
                'status' => ApplicationStatus::Rejected,
                'rejected_by' => $by->getKey(),
                'rejected_at' => now(),
                'rejected_stage' => $application->status === ApplicationStatus::Submitted ? 'chapter' : 'state',
                'rejection_reason' => $reason,
            ]);

            // A rejected applicant is not a member school; drop it from the registry.
            $application->school->delete();
        });

        $application->applicant->notify(new ApplicationUpdate($application));

        return $application;
    }

    private function guard(MembershipApplication $application, ApplicationStatus $expected): void
    {
        if ($application->status !== $expected) {
            throw new DomainException(__('This application is :status and cannot be changed that way.', [
                'status' => mb_strtolower($application->status->label()),
            ]));
        }
    }

    private function notifyOfficers(MembershipApplication $application, Role $role, ScopeLevel $level, ?int $scopeId): void
    {
        Notification::send(
            User::query()->holdingRole($role, $level, $scopeId)->get(),
            new ApplicationNeedsReview($application, $role),
        );
    }
}
