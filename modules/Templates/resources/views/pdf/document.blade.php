<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $template->name }}</title>
    <style>
        @page { margin: 22mm 20mm 24mm; }
        body { font-family: 'DejaVu Sans', sans-serif; color: #1c1917; font-size: 10.5pt; line-height: 1.55; }
        h1 { font-size: 16pt; text-align: center; text-transform: uppercase; margin: 0 0 6mm; }
        h2 { font-size: 12pt; margin: 6mm 0 2mm; }
        p { margin: 0 0 3mm; }
        ol, ul { margin: 0 0 3mm; padding-left: 6mm; }
        li { margin-bottom: 1.5mm; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 1.5mm 2mm; vertical-align: top; }
        .blank { color: #78716c; }
        .right { text-align: right; }
        .center { text-align: center; }
        .signature { margin-top: 14mm; }
        .signature td { width: 50%; padding-top: 10mm; }
        .foot { position: fixed; bottom: -14mm; left: 0; right: 0; font-size: 8pt; color: #78716c; border-top: 1px solid #d6d3d1; padding-top: 2mm; }
    </style>
</head>
<body>
    <div class="foot">
        {{ config('napps.full_name') }} &middot; {{ $template->name }} &middot; {{ __('version :v', ['v' => $template->version]) }}
        @unless ($template->isReviewed())
            &middot; {{ __('Not yet legally reviewed') }}
        @endunless
    </div>

    {!! $body !!}
</body>
</html>
