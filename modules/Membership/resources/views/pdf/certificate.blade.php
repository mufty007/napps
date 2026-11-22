<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ __('Certificate of membership') }} · {{ $school->name }}</title>
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'DejaVu Sans', sans-serif; color: #1c1917; }
        .page { position: absolute; top: 14mm; right: 14mm; bottom: 14mm; left: 14mm; border: 3px solid #13613f; }
        .inner { position: absolute; top: 3mm; right: 3mm; bottom: 3mm; left: 3mm; border: 1px solid #f2b632; padding: 12mm 18mm; }
        .org { text-align: center; font-size: 11pt; letter-spacing: 3px; text-transform: uppercase; color: #13613f; font-weight: bold; }
        .title { text-align: center; font-size: 30pt; margin: 6mm 0 2mm; color: #0f3f2b; font-weight: bold; }
        .lead { text-align: center; font-size: 11pt; color: #57534e; }
        .school { text-align: center; font-size: 24pt; font-weight: bold; margin: 6mm 0 2mm; color: #1c1917; }
        .place { text-align: center; font-size: 11pt; color: #44403c; }
        .body { text-align: center; font-size: 10.5pt; color: #44403c; margin: 6mm 22mm 0; line-height: 1.5; }
        table.foot { position: absolute; left: 18mm; right: 18mm; bottom: 10mm; width: auto; border-collapse: collapse; }
        table.foot td { vertical-align: bottom; font-size: 9pt; color: #57534e; }
        .sign { border-top: 1px solid #a8a29e; padding-top: 2mm; width: 58mm; text-align: center; }
        .meta strong { display: block; font-size: 12pt; color: #1c1917; }
        .qr { text-align: center; }
        .qr img { width: 32mm; height: 32mm; }
        .small { font-size: 7.5pt; color: #78716c; }
    </style>
</head>
<body>
<div class="page">
    <div class="inner">
        <div class="org">{{ config('napps.full_name') }}</div>
        <div class="title">{{ __('Certificate of Membership') }}</div>
        <div class="lead">{{ __('This is to certify that') }}</div>
        <div class="school">{{ $school->name }}</div>
        <div class="place">{{ $school->address ? $school->address.', ' : '' }}{{ $school->chapter?->name }}, {{ $school->state?->name }} {{ __('State') }}</div>
        <div class="body">
            {{ __('is a registered member school of the :org, verified by its chapter and approved by its state, and entitled to the rights and privileges of membership while in good standing.', ['org' => config('napps.full_name')]) }}
        </div>

        <table class="foot">
            <tr>
                <td class="meta" style="width: 34%">
                    {{ __('Membership number') }}
                    <strong>{{ $school->membership_number }}</strong>
                    <br>
                    {{ __('Member since') }}
                    <strong>{{ $memberSince->format('j F Y') }}</strong>
                </td>
                <td style="width: 33%">
                    <div class="sign">{{ __('National President') }}</div>
                    <br>
                    <div class="sign">{{ __('National Secretary') }}</div>
                </td>
                <td class="qr" style="width: 33%">
                    <img src="{{ $qr }}" alt="">
                    <div class="small">{{ __('Scan to check current status') }}</div>
                    <div class="small">{{ $verifyUrl }}</div>
                </td>
            </tr>
        </table>
    </div>
</div>
</body>
</html>
