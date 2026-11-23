<?php

namespace Modules\Records\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Records\Models\Student;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Results, ID and other papers filed on a pupil's record. */
class StudentDocumentController extends Controller
{
    public function store(Request $request, Student $student): RedirectResponse
    {
        Gate::authorize('update', $student);

        $validated = $request->validate([
            'collection' => ['required', Rule::in(array_keys(Student::DOCUMENTS))],
            'title' => ['nullable', 'string', 'max:150'],
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp'],
        ]);

        $adder = $student->addMedia($request->file('file'))
            ->withCustomProperties(['uploaded_by' => $request->user()->getKey()]);

        if (filled($validated['title'] ?? null)) {
            $adder->usingName(trim($validated['title']));
        }

        $media = $adder->toMediaCollection($validated['collection']);

        activity('records')->performedOn($student)->causedBy($request->user())->event('document-added')
            ->withProperties(['media_id' => $media->getKey(), 'collection' => $validated['collection']])
            ->log('Added a pupil document');

        return back()->with('status', __('Document added.'));
    }

    public function show(Request $request, Student $student, Media $media): StreamedResponse
    {
        Gate::authorize('view', $student);
        $this->ensureBelongsTo($student, $media);

        activity('records')->performedOn($student)->causedBy($request->user())->event('document-viewed')
            ->withProperties(['media_id' => $media->getKey(), 'collection' => $media->collection_name])
            ->log('Viewed a pupil document');

        return $media->toInlineResponse($request);
    }

    public function destroy(Request $request, Student $student, Media $media): RedirectResponse
    {
        Gate::authorize('update', $student);
        $this->ensureBelongsTo($student, $media);

        activity('records')->performedOn($student)->causedBy($request->user())->event('document-removed')
            ->withProperties(['media_id' => $media->getKey(), 'collection' => $media->collection_name, 'name' => $media->name])
            ->log('Removed a pupil document');

        $media->delete();

        return back()->with('status', __('Document removed.'));
    }

    private function ensureBelongsTo(Student $student, Media $media): void
    {
        abort_unless($media->model_type === $student->getMorphClass() && (int) $media->model_id === $student->getKey(), 404);
    }
}
