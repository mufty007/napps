<?php

namespace Modules\Claims\Http\Controllers;

use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Claims\Http\Controllers\Concerns\RespondsToAjax;
use Modules\Claims\Models\ClaimCase;
use Modules\Claims\Services\ClaimService;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The claim pack: upload, view and generate the documents on a case. */
class ClaimDocumentController extends Controller
{
    use RespondsToAjax;

    public function __construct(private readonly ClaimService $claims) {}

    public function store(Request $request, ClaimCase $case): RedirectResponse|JsonResponse
    {
        Gate::authorize('contribute', $case);

        // The owning school adds to the pack; pleadings and judgments are the legal team's.
        $allowed = Gate::allows('handle', $case) ? array_keys(ClaimCase::DOCUMENTS) : ClaimCase::SCHOOL_DOCUMENTS;

        $validated = $request->validate([
            'collection' => ['required', Rule::in($allowed)],
            'title' => ['nullable', 'string', 'max:150'],
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx'],
        ]);

        try {
            $this->claims->addDocument($case, $request->user(), $request->file('file'), $validated['collection'], $validated['title'] ?? null);
        } catch (DomainException $e) {
            return $this->fail($request, $e->getMessage());
        }

        return $this->respond($request, __('Document added.'));
    }

    public function show(Request $request, ClaimCase $case, Media $media): StreamedResponse
    {
        Gate::authorize('view', $case);
        abort_unless($media->model_type === $case->getMorphClass() && (int) $media->model_id === $case->getKey(), 404);

        activity('claims')->performedOn($case)->causedBy($request->user())->event('document-viewed')
            ->withProperties(['media_id' => $media->getKey(), 'collection' => $media->collection_name])
            ->log('Viewed claim document');

        return $media->toInlineResponse($request);
    }

    /** Fill the library's demand letter for this claim and attach it. */
    public function demandLetter(Request $request, ClaimCase $case): RedirectResponse|JsonResponse
    {
        Gate::authorize('contribute', $case);

        try {
            $this->claims->generateDemandLetter($case, $request->user());
        } catch (DomainException $e) {
            return $this->fail($request, $e->getMessage());
        }

        return $this->respond($request, __('Demand letter generated.'));
    }
}
