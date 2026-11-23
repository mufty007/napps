<?php

namespace App\Http\Controllers;

use App\Enums\MembershipStatus;
use App\Models\Chapter;
use App\Models\School;
use App\Models\State;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * The public landing page, with a few live association figures.
 */
class PublicHomeController extends Controller
{
    public function __invoke(): View
    {
        $stats = Cache::remember('public.home.stats', now()->addMinutes(15), fn (): array => [
            'schools' => School::withoutGlobalScopes()->whereIn('membership_status', MembershipStatus::members())->count(),
            'chapters' => Chapter::query()->count(),
            'states' => State::query()->count(),
        ]);

        return view('public.home', ['stats' => $stats]);
    }
}
