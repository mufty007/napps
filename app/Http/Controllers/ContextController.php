<?php

namespace App\Http\Controllers;

use App\Models\RoleAssignment;
use App\Support\Scope\ActiveContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Role switcher: one person, several roles (e.g. proprietor + chapter chair).
 */
class ContextController extends Controller
{
    public function update(Request $request, ActiveContext $context): RedirectResponse|JsonResponse
    {
        $validated = $request->validate(['assignment' => ['required', 'integer']]);

        $assignment = RoleAssignment::query()
            ->where('user_id', $request->user()->getKey())
            ->findOrFail($validated['assignment']);

        $context->switchTo($assignment);

        activity('roles')
            ->performedOn($assignment)
            ->event('switched')
            ->log('Switched active role');

        $status = __('You are now working as :role.', ['role' => $assignment->label()]);

        if ($request->expectsJson() || $request->ajax()) {
            $redirect = $request->input('redirect');
            if (! is_string($redirect) || ! str_starts_with($redirect, url('/'))) {
                $redirect = route('start');
            }

            return response()->json([
                'status' => $status,
                'redirect' => $redirect,
                'label' => $assignment->label(),
            ]);
        }

        return redirect()->route('start')->with('status', $status);
    }
}
