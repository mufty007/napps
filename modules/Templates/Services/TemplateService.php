<?php

namespace Modules\Templates\Services;

use App\Support\Money;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\Templates\Models\DocumentTemplate;
use Throwable;

/**
 * Fills a template's {{placeholders}} and turns it into a PDF.
 *
 * Values are always HTML-escaped; only the template body itself is trusted
 * markup (publishers write it). A placeholder left blank prints as a line to
 * write on, so a half-filled form still makes a usable document.
 */
class TemplateService
{
    public const PLACEHOLDER_PATTERN = '/\{\{\s*([a-z][a-z0-9_]*)\s*\}\}/i';

    /**
     * PDF binary for a template (model or slug) filled with the given values.
     *
     * @param  array<string, mixed>  $vars  placeholder key => value (money in naira as typed)
     */
    public function render(DocumentTemplate|string $template, array $vars = []): string
    {
        $template = $this->resolve($template);

        return Pdf::loadView('templates::pdf.document', [
            'template' => $template,
            'body' => $this->renderHtml($template, $vars),
        ])->setPaper('a4')->output();
    }

    /**
     * The template body with values substituted.
     *
     * @param  array<string, mixed>  $vars
     * @param  bool  $preview  show unfilled placeholders as highlighted labels instead of blank lines
     */
    public function renderHtml(DocumentTemplate|string $template, array $vars = [], bool $preview = false): string
    {
        $template = $this->resolve($template);
        $fields = $this->fields($template);

        return (string) preg_replace_callback(
            self::PLACEHOLDER_PATTERN,
            function (array $match) use ($vars, $fields, $preview) {
                $key = strtolower($match[1]);
                $value = $vars[$key] ?? null;
                $type = $fields[$key]['type'] ?? $this->inferType($key);

                if ($value === null || trim((string) $value) === '') {
                    return $preview
                        ? '<mark class="ph">['.e($fields[$key]['label'] ?? $this->labelFor($key)).']</mark>'
                        : '<span class="blank">______________________</span>';
                }

                return $this->format((string) $value, $type);
            },
            $template->body_html,
        );
    }

    /**
     * Fill-in fields found in an HTML body, in order of first appearance.
     *
     * @return array<string, array{label: string, type: string}>
     */
    public function extractPlaceholders(string $html): array
    {
        preg_match_all(self::PLACEHOLDER_PATTERN, $html, $matches);

        $fields = [];
        foreach ($matches[1] as $key) {
            $key = strtolower($key);
            $fields[$key] ??= ['label' => $this->labelFor($key), 'type' => $this->inferType($key)];
        }

        return $fields;
    }

    /**
     * The template's fields: stored definitions, or derived from the body.
     *
     * @return array<string, array{label: string, type: string}>
     */
    public function fields(DocumentTemplate $template): array
    {
        $stored = $template->placeholders;

        return is_array($stored) && $stored !== []
            ? $stored
            : $this->extractPlaceholders($template->body_html);
    }

    public function filename(DocumentTemplate $template): string
    {
        return Str::slug($template->name).'-v'.$template->version.'.pdf';
    }

    private function resolve(DocumentTemplate|string $template): DocumentTemplate
    {
        return $template instanceof DocumentTemplate
            ? $template
            : DocumentTemplate::query()->where('slug', $template)->firstOrFail();
    }

    private function format(string $value, string $type): string
    {
        if ($type === 'money') {
            $kobo = Money::toKobo($value);

            return $kobo !== null ? e(Money::format($kobo)) : e($value);
        }

        if ($type === 'date') {
            try {
                return e(Carbon::parse($value)->format('j F Y'));
            } catch (Throwable) {
                return e($value);
            }
        }

        return nl2br(e($value), false);
    }

    private function labelFor(string $key): string
    {
        return Str::of($key)->replace('_', ' ')->ucfirst()->toString();
    }

    private function inferType(string $key): string
    {
        return match (true) {
            str_ends_with($key, '_words') => 'text',
            $key === 'date' || str_ends_with($key, '_date') || str_starts_with($key, 'date_') => 'date',
            (bool) preg_match('/(^|_)(amount|rent|fee|fees|balance|sum|deposit|total)(_|$)/', $key) => 'money',
            (bool) preg_match('/(^|_)(terms|reason|details|particulars|notes|address)(_|$)/', $key) => 'textarea',
            default => 'text',
        };
    }
}
