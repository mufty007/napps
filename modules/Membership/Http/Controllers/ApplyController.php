<?php

namespace Modules\Membership\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Chapter;
use App\Models\State;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Membership\Enums\ApplicationStatus;
use Modules\Membership\Http\Requests\SubmitApplicationRequest;
use Modules\Membership\Models\MembershipApplication;
use Modules\Membership\Services\ApplicationWorkflow;
use Modules\Membership\Services\MembershipDocuments;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * MEM-1 for the applicant. Applicants usually hold no role yet, so their
 * applications are reached through "{myApplication}", which only ever
 * resolves to the signed-in person's own applications.
 */
class ApplyController extends Controller
{
    public function create(Request $request): View
    {
        $open = MembershipApplication::withoutGlobalScopes()
            ->where('applicant_id', $request->user()->getKey())
            ->whereIn('status', [ApplicationStatus::Submitted, ApplicationStatus::Verified])
            ->count();

        return view('membership::apply.create', [
            'user' => $request->user(),
            'states' => State::query()->orderBy('name')->get(['id', 'name']),
            'chapters' => Chapter::query()->orderBy('name')->get(['id', 'state_id', 'name']),
            'limitReached' => $open >= config('napps.membership.max_open_applications'),
        ]);
    }

    public function store(SubmitApplicationRequest $request, ApplicationWorkflow $workflow): RedirectResponse
    {
        $application = $workflow->submit($request->user(), $request->schoolData(), $request->documents());

        return redirect()
            ->route('membership.apply.show', $application)
            ->with('status', __('Application sent. :chapter has been notified.', ['chapter' => $application->school->chapter?->name]));
    }

    public function show(MembershipApplication $myApplication, MembershipDocuments $documents): View
    {
        $myApplication->load(['school.chapter', 'school.state', 'media']);

        return view('membership::apply.show', [
            'application' => $myApplication,
            'certificateUrl' => $myApplication->status === ApplicationStatus::Approved && $myApplication->school->membership_number
                ? $documents->certificateUrl($myApplication->school, 1)
                : null,
        ]);
    }

    public function document(MembershipApplication $myApplication, string $collection): StreamedResponse
    {
        abort_unless(array_key_exists($collection, MembershipApplication::DOCUMENTS), 404);

        $media = $myApplication->getFirstMedia($collection);

        abort_if($media === null, 404);

        return $media->toInlineResponse(request());
    }
}
