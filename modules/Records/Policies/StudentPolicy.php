<?php

namespace Modules\Records\Policies;

use App\Enums\Role;
use App\Models\User;
use App\Support\Scope\ActiveContext;
use Modules\Records\Models\Student;

/**
 * Who may keep a school's pupil records. The permission comes from the active
 * role; *which* pupils comes from the school scope on the Student model, so a
 * record from another school is never even resolved for the person asking.
 */
class StudentPolicy
{
    public function __construct(private readonly ActiveContext $context) {}

    public function viewAny(User $user): bool
    {
        return $this->context->allows('records.students.manage');
    }

    public function view(User $user, Student $student): bool
    {
        return $this->viewAny($user) && $this->inScope($student);
    }

    /** Enrol a pupil: at the active school, or anywhere for the secretariat. */
    public function create(User $user): bool
    {
        return $this->viewAny($user)
            && ($this->context->schoolId() !== null || $this->context->is(Role::Secretariat));
    }

    public function update(User $user, Student $student): bool
    {
        return $this->view($user, $student);
    }

    public function delete(User $user, Student $student): bool
    {
        return $this->view($user, $student);
    }

    /** Edit the fees-owed figure: bursars and proprietors, who also handle clearance. */
    public function setBalance(User $user): bool
    {
        return $this->context->allows('records.transfers.manage');
    }

    private function inScope(Student $student): bool
    {
        return Student::query()->whereKey($student->getKey())->exists();
    }
}
