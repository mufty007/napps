<?php

namespace Modules\Claims\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\Claims\Http\Controllers\Concerns\RespondsToAjax;
use Modules\Claims\Models\ClaimCase;
use Modules\Claims\Models\ClaimHearing;
use Modules\Claims\Services\ClaimService;

class ClaimHearingController extends Controller
{
    use RespondsToAjax;

    public function __construct(private readonly ClaimService $claims) {}

    public function store(Request $request, ClaimCase $case): RedirectResponse|JsonResponse
    {
        Gate::authorize('handle', $case);

        $validated = $request->validate([
            'scheduled_at' => ['required', 'date'],
            'venue' => ['nullable', 'string', 'max:255'],
        ]);

        $this->claims->scheduleHearing(
            $case,
            $request->user(),
            CarbonImmutable::parse($validated['scheduled_at']),
            $validated['venue'] ?? null,
        );

        return $this->respond($request, __('Hearing scheduled.'));
    }

    /** Reschedule, change the venue or record the outcome. */
    public function update(Request $request, ClaimCase $case, ClaimHearing $hearing): RedirectResponse|JsonResponse
    {
        abort_unless($hearing->claim_case_id === $case->getKey(), 404);
        Gate::authorize('handle', $case);

        $validated = $request->validate([
            'scheduled_at' => ['nullable', 'date'],
            'venue' => ['nullable', 'string', 'max:255'],
            'outcome' => ['nullable', 'string', 'max:3000'],
        ]);

        $data = array_intersect_key($validated, array_flip(array_keys($request->all())));

        if (filled($data['scheduled_at'] ?? null)) {
            $data['scheduled_at'] = CarbonImmutable::parse($data['scheduled_at']);
        } else {
            unset($data['scheduled_at']);
        }

        $this->claims->updateHearing($hearing->setRelation('claimCase', $case), $request->user(), $data);

        return $this->respond($request, __('Hearing updated.'));
    }
}
