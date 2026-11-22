<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ __('Receipt') }} {{ $payment->receipt_number }}</title>
    <style>
        @page { margin: 18mm 16mm; }
        body { font-family: 'DejaVu Sans', sans-serif; color: #1c1917; font-size: 10pt; }
        .head { border-bottom: 3px solid #13613f; padding-bottom: 4mm; }
        .org { font-size: 13pt; font-weight: bold; color: #13613f; }
        .sub { color: #57534e; font-size: 9pt; }
        h1 { font-size: 18pt; margin: 8mm 0 1mm; }
        .muted { color: #78716c; }
        table { width: 100%; border-collapse: collapse; }
        .info td { padding: 1.5mm 0; vertical-align: top; }
        .info td.label { color: #78716c; width: 38mm; }
        .lines { margin-top: 8mm; }
        .lines th { text-align: left; font-size: 8.5pt; text-transform: uppercase; letter-spacing: 0.5px; color: #78716c; border-bottom: 1px solid #d6d3d1; padding: 2mm 0; }
        .lines td { padding: 3mm 0; border-bottom: 1px solid #e7e5e4; }
        .right { text-align: right; }
        .total td { font-weight: bold; font-size: 12pt; border-bottom: none; padding-top: 4mm; }
        .paid { display: inline-block; margin-top: 8mm; padding: 2mm 5mm; border: 2px solid #17794d; color: #17794d; font-weight: bold; letter-spacing: 2px; }
        .note { margin-top: 6mm; padding: 3mm 4mm; background: #fef3c7; color: #78350f; font-size: 9pt; }
        .foot { margin-top: 12mm; font-size: 8.5pt; color: #78716c; }
    </style>
</head>
<body>
    <div class="head">
        <div class="org">{{ config('napps.full_name') }}</div>
        <div class="sub">{{ __('Membership dues receipt') }}</div>
    </div>

    <h1>{{ __('Receipt') }} {{ $payment->receipt_number }}</h1>
    <div class="muted">{{ __('Issued :date', ['date' => $payment->paid_at->format('j F Y, g:ia')]) }}</div>

    <table class="info" style="margin-top: 6mm">
        <tr><td class="label">{{ __('School') }}</td><td><strong>{{ $school->name }}</strong></td></tr>
        <tr><td class="label">{{ __('Membership number') }}</td><td>{{ $school->membership_number ?? '—' }}</td></tr>
        <tr><td class="label">{{ __('Chapter') }}</td><td>{{ $school->chapter?->name }}, {{ $school->state?->name }}</td></tr>
        <tr><td class="label">{{ __('Paid by') }}</td><td>{{ $payment->payer?->name ?? $payment->recorder?->name ?? '—' }}</td></tr>
        <tr><td class="label">{{ __('Method') }}</td><td>{{ $payment->method->label() }}{{ $payment->reference ? ' · '.$payment->reference : '' }}</td></tr>
        @if ($payment->recorder)
            <tr><td class="label">{{ __('Recorded by') }}</td><td>{{ $payment->recorder->name }}</td></tr>
        @endif
    </table>

    <table class="lines">
        <thead>
            <tr><th>{{ __('Description') }}</th><th>{{ __('Set by') }}</th><th class="right">{{ __('Amount') }}</th></tr>
        </thead>
        <tbody>
            @foreach ($payment->dues as $due)
                <tr>
                    <td>{{ $due->charge->title }}</td>
                    <td>{{ $due->charge->placeName() }}</td>
                    <td class="right">{{ \App\Support\Money::format($due->amount_kobo) }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="2">{{ __('Total received') }}</td>
                <td class="right">{{ \App\Support\Money::format($payment->amount_kobo) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="paid">{{ __('PAID') }}</div>

    @if ($payment->notes)
        <div class="note">{!! nl2br(e($payment->notes)) !!}</div>
    @endif

    <div class="foot">
        {{ __('Keep this receipt for your records.') }}
        @if ($verifyUrl)
            {{ __('Check the school\'s membership status at :url', ['url' => $verifyUrl]) }}
        @endif
    </div>
</body>
</html>
