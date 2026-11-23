<?php

namespace Modules\Records\Policies;

use App\Models\User;
use App\Support\Scope\ActiveContext;
use Modules\Records\Models\TransferRequest;

/**
 * Both schools see a clearance request; only the school that holds the pupil's
 * record may answer it, and only the asking side may withdraw it.
 */
class TransferRequestPolicy
{
    public function __construct(private readonly ActiveContext $context) {}

    public function viewAny(User $user): bool
    {
        return $this->context->allows('records.transfers.manage');
    }

    public function view(User $user, TransferRequest $request): bool
    {
        return $this->viewAny($user)
            && TransferRequest::query()->visibleTo($this->context)->whereKey($request->getKey())->exists();
    }

    /** Ask another school to clear a pupil. */
    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    /** Answer: the holding school, while the request is pending. */
    public function respond(User $user, TransferRequest $request): bool
    {
        return $this->viewAny($user)
            && $request->isPending()
            && TransferRequest::query()->heldBy($this->context)->whereKey($request->getKey())->exists();
    }

    /** Withdraw: the asking school (or whoever filed it), while pending. */
    public function cancel(User $user, TransferRequest $request): bool
    {
        return $this->viewAny($user)
            && $request->isPending()
            && ($request->requested_by === $user->getKey()
                || TransferRequest::query()->askedBy($this->context)->whereKey($request->getKey())->exists());
    }
}
