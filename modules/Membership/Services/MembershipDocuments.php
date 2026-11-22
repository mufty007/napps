<?php

namespace Modules\Membership\Services;

use App\Models\School;
use App\Support\QrCode;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\URL;
use Modules\Membership\Enums\ApplicationStatus;
use Modules\Membership\Models\DuesPayment;
use Modules\Membership\Models\MembershipApplication;

/**
 * Membership certificate (with a QR code to the public verify page) and dues
 * receipts, rendered on demand so they always show the current details.
 *
 * Links to them are temporary signed URLs: they work from an SMS for a
 * proprietor whose active school is a different one, and screens only show
 * them to people allowed to see the document.
 */
class MembershipDocuments
{
    public function certificate(School $school): PdfDocument
    {
        $school->loadMissing(['chapter', 'state']);
        $verifyUrl = route('verify.show', $school->membership_number);

        return Pdf::loadView('membership::pdf.certificate', [
            'school' => $school,
            'memberSince' => $this->memberSince($school),
            'verifyUrl' => $verifyUrl,
            'qr' => QrCode::pngDataUri($verifyUrl, 320),
        ])->setPaper('a4', 'landscape');
    }

    public function receipt(DuesPayment $payment): PdfDocument
    {
        $payment->loadMissing(['dues.charge', 'payer', 'recorder']);
        $school = $payment->school()->withoutGlobalScopes()->with(['chapter', 'state'])->firstOrFail();

        return Pdf::loadView('membership::pdf.receipt', [
            'payment' => $payment,
            'school' => $school,
            'verifyUrl' => $school->membership_number ? route('verify.show', $school->membership_number) : null,
        ])->setPaper('a4');
    }

    public function memberSince(School $school): CarbonInterface
    {
        $approvedAt = MembershipApplication::withoutGlobalScopes()
            ->where('school_id', $school->getKey())
            ->where('status', ApplicationStatus::Approved)
            ->value('approved_at');

        return $approvedAt ?? $school->created_at;
    }

    public function certificateUrl(School $school, int $days = 30): string
    {
        return URL::temporarySignedRoute('membership.certificate', now()->addDays($days), ['school' => $school->getKey()]);
    }

    public function receiptUrl(DuesPayment $payment, int $days = 90): string
    {
        return URL::temporarySignedRoute('membership.receipt', now()->addDays($days), ['payment' => $payment->getKey()]);
    }
}
