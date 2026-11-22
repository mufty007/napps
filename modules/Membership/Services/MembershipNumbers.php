<?php

namespace Modules\Membership\Services;

use App\Models\School;
use App\Models\State;
use Illuminate\Support\Facades\DB;

/**
 * Membership numbers: NAPPS-{state code}-{sequence}, e.g. NAPPS-KN-00042.
 * Sequential per state; the state row is locked while one is assigned and
 * the column is unique, so two approvals can never share a number.
 */
class MembershipNumbers
{
    public const PATTERN = '/^NAPPS-[A-Z]{2}-\d{5,}$/';

    public function assign(School $school): string
    {
        if ($school->membership_number) {
            return $school->membership_number;
        }

        return DB::transaction(function () use ($school) {
            $state = State::query()->lockForUpdate()->findOrFail($school->state_id);
            $prefix = 'NAPPS-'.$state->code.'-';

            // Longest, then highest: keeps 100000 after 99999.
            $last = School::withoutGlobalScopes()
                ->where('membership_number', 'like', $prefix.'%')
                ->orderByRaw('length(membership_number) desc')
                ->orderByDesc('membership_number')
                ->value('membership_number');

            $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;
            $number = $prefix.str_pad((string) $next, 5, '0', STR_PAD_LEFT);

            $school->forceFill(['membership_number' => $number])->save();

            return $number;
        });
    }

    public static function looksLikeOne(string $value): bool
    {
        return (bool) preg_match(self::PATTERN, strtoupper(trim($value)));
    }
}
