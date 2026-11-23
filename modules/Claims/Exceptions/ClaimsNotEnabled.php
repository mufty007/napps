<?php

namespace Modules\Claims\Exceptions;

use DomainException;

/** Thrown when a school's state has not switched on debt claims. */
class ClaimsNotEnabled extends DomainException
{
    public static function forState(?string $state): self
    {
        return new self(__('Debt claims are not switched on for :state yet. Ask the NAPPS secretariat.', [
            'state' => $state ?? __('your state'),
        ]));
    }
}
