<?php

namespace Modules\Claims\Enums;

/** What a row in a claim's activity timeline records. */
enum ClaimEventType: string
{
    case StageChange = 'stage_change';
    case Note = 'note';
    case Hearing = 'hearing';
    case Filing = 'filing';
    case Payment = 'payment';
    case Assignment = 'assignment';
    case Document = 'document';
    case Settlement = 'settlement';

    public function label(): string
    {
        return match ($this) {
            self::StageChange => __('Stage changed'),
            self::Note => __('Note'),
            self::Hearing => __('Hearing'),
            self::Filing => __('Court details'),
            self::Payment => __('Fee recorded'),
            self::Assignment => __('Lawyer assigned'),
            self::Document => __('Document'),
            self::Settlement => __('Settlement'),
        };
    }

    /** Notes are internal to the legal team and the association; schools do not see them. */
    public function isInternal(): bool
    {
        return $this === self::Note;
    }
}
