<?php

namespace Modules\Membership\Http\Controllers;

use App\Enums\MembershipStatus;
use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Modules\Membership\Models\DuesPayment;
use Modules\Membership\Services\MembershipDocuments;

/**
 * Certificate and receipt PDFs. Both routes require a valid signature
 * (see MembershipDocuments::*Url), which screens and notifications only
 * hand to people allowed to see the document.
 */
class DocumentController extends Controller
{
    public function certificate(int $school, MembershipDocuments $documents): Response
    {
        $school = School::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->whereIn('membership_status', MembershipStatus::members())
            ->whereNotNull('membership_number')
            ->findOrFail($school);

        return $documents->certificate($school)->stream('napps-certificate-'.Str::slug($school->membership_number).'.pdf');
    }

    public function receipt(int $payment, MembershipDocuments $documents): Response
    {
        $payment = DuesPayment::withoutGlobalScopes()->findOrFail($payment);

        return $documents->receipt($payment)->stream($payment->receipt_number.'.pdf');
    }
}
