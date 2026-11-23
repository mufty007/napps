<?php

namespace Modules\Leases\Http\Controllers;

use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\Leases\Models\Lease;
use Modules\Leases\Services\LeaseService;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The lease vault: upload, view and generate the documents on a lease. */
class LeaseDocumentController extends Controller
{
    public function __construct(private readonly LeaseService $leases) {}

    public function store(Request $request, Lease $lease): RedirectResponse
    {
        Gate::authorize('manage', $lease);

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:150'],
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx'],
        ]);

        $this->leases->addDocument($lease, $request->user(), $request->file('file'), $validated['title'] ?? null);

        return redirect()->route('leases.show', $lease)->with('status', __('Document added to the vault.'));
    }

    public function show(Request $request, Lease $lease, Media $media): StreamedResponse
    {
        Gate::authorize('view', $lease);
        abort_unless(
            $media->model_type === $lease->getMorphClass()
                && (int) $media->model_id === $lease->getKey()
                && $media->collection_name === Lease::VAULT,
            404,
        );

        activity('leases')->performedOn($lease)->causedBy($request->user())->event('document-viewed')
            ->withProperties(['media_id' => $media->getKey()])
            ->log('Viewed lease document');

        return $media->toInlineResponse($request);
    }

    /** Draft a lease from the library's model lease template and file it. */
    public function modelLease(Request $request, Lease $lease): RedirectResponse
    {
        Gate::authorize('manage', $lease);

        try {
            $this->leases->generateModelLease($lease, $request->user());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('leases.show', $lease)->with('status', __('Model lease drafted and added to the vault.'));
    }
}
