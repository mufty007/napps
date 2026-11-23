<?php

namespace App\Http\Middleware;

use App\Support\Scope\ActiveContext;
use App\Support\TwoFactor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * PRD: two-factor authentication is required for executives and bursars.
 * Until they turn it on, those roles can only reach their account settings.
 */
class EnsureTwoFactorEnabled
{
    public function __construct(private readonly ActiveContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $role = $this->context->role();

        if (TwoFactor::enforced() && $user && $role?->requiresTwoFactor() && ! $user->hasConfirmedTwoFactor()) {
            if ($request->expectsJson()) {
                abort(403, __('Two-factor authentication is required for your role.'));
            }

            return redirect()->route('account.show')->with(
                'warning',
                __('Your role as :role needs two-factor authentication. Turn it on below to continue.', ['role' => $role->label()]),
            );
        }

        return $next($request);
    }
}
