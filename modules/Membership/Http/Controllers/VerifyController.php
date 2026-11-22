<?php

namespace Modules\Membership\Http\Controllers;

use App\Enums\MembershipStatus;
use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\Scopes\SchoolVisibilityScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Modules\Membership\Services\MembershipDocuments;
use Modules\Membership\Services\MembershipNumbers;

/**
 * Public "verify a member school" page (PRD) and the target of the QR code
 * on certificates. Shows membership standing only: dues details stay
 * private, so an owing school still reads as a verified member.
 */
class VerifyController extends Controller
{
    public function index(Request $request): View
    {
        $query = trim((string) $request->query('q'));
        $results = collect();

        if (mb_strlen($query) >= 3) {
            $results = $this->members()
                ->with(['chapter', 'state'])
                ->where(fn (Builder $q) => MembershipNumbers::looksLikeOne($query)
                    ? $q->where('membership_number', strtoupper($query))
                    : $q->where('name', 'like', '%'.$query.'%'))
                ->orderBy('name')
                ->limit(10)
                ->get();
        }

        return view('membership::verify.index', ['query' => $query, 'results' => $results]);
    }

    public function show(string $number, MembershipDocuments $documents): Response
    {
        $school = $this->members()
            ->with(['chapter', 'state'])
            ->where('membership_number', strtoupper($number))
            ->first();

        return response()->view('membership::verify.show', [
            'number' => strtoupper($number),
            'school' => $school,
            'memberSince' => $school ? $documents->memberSince($school) : null,
        ], $school ? 200 : 404);
    }

    /**
     * Approved member schools, whoever is looking (guests and signed-in roles alike).
     *
     * @return Builder<School>
     */
    private function members(): Builder
    {
        return School::query()
            ->withoutGlobalScope(SchoolVisibilityScope::class)
            ->whereIn('membership_status', MembershipStatus::members())
            ->whereNotNull('membership_number');
    }
}
