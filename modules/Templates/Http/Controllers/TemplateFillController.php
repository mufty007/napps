<?php

namespace Modules\Templates\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Support\Money;
use App\Support\Scope\ActiveContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Templates\Models\DocumentTemplate;
use Modules\Templates\Services\TemplateService;

class TemplateFillController extends Controller
{
    public function __construct(private readonly TemplateService $templates) {}

    public function create(ActiveContext $context, DocumentTemplate $template): View
    {
        $this->authorizeTemplate($template);

        $schoolId = $context->schoolId();

        return view('templates::fill.create', [
            'template' => $template,
            'fields' => $this->templates->fields($template),
            'defaults' => [
                'date' => now()->toDateString(),
                'school_name' => $schoolId ? (School::withoutGlobalScopes()->whereKey($schoolId)->value('name') ?? '') : '',
            ],
        ]);
    }

    public function store(Request $request, DocumentTemplate $template): Response
    {
        $this->authorizeTemplate($template);

        $fields = $this->templates->fields($template);
        $rules = [];

        foreach ($fields as $key => $field) {
            // Inputs are named after the placeholder key so old() and error bags line up.
            $rules[$key] = match ($field['type']) {
                'date' => ['nullable', 'date'],
                'money' => ['nullable', 'string', 'max:30'],
                'textarea' => ['nullable', 'string', 'max:3000'],
                default => ['nullable', 'string', 'max:255'],
            };
        }

        $values = $request->validate($rules);

        $errors = [];
        foreach ($fields as $key => $field) {
            $value = $values[$key] ?? null;
            if ($field['type'] === 'money' && filled($value) && Money::toKobo($value) === null) {
                $errors[$key] = __('Enter :label as an amount in naira, e.g. 25000.', ['label' => strtolower($field['label'])]);
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $pdf = $this->templates->render($template, $values);

        activity('templates')->performedOn($template)->causedBy($request->user())
            ->withProperties(['version' => $template->version])
            ->log('exported');

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->templates->filename($template).'"',
        ]);
    }

    private function authorizeTemplate(DocumentTemplate $template): void
    {
        abort_unless(Gate::any(['templates.use', 'templates.publish']), 403);
        abort_unless($template->isPublished() || Gate::allows('templates.publish'), 404);
    }
}
