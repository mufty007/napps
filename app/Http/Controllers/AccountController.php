<?php

namespace App\Http\Controllers;

use App\Support\Scope\ActiveContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Profile, password and two-factor settings. The forms post to Fortify's
 * endpoints (profile-information, password, two-factor-*).
 */
class AccountController extends Controller
{
    public function show(Request $request, ActiveContext $context): View
    {
        $user = $request->user();

        return view('account.show', [
            'user' => $user,
            'assignments' => $context->assignments(),
            'twoFactorRequired' => $context->assignments()->contains(fn ($a) => $a->roleKey()?->requiresTwoFactor()),
            'twoFactorPending' => $user->two_factor_secret !== null && $user->two_factor_confirmed_at === null,
        ]);
    }
}
