<?php

namespace Modules\Claims\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Case forms work as plain posts and as ajax (resources/js/ajax.js `ajaxForm`):
 * ajax callers get JSON and reload to show the new timeline entry.
 */
trait RespondsToAjax
{
    /**
     * @param  array<string, mixed>  $extra
     */
    protected function respond(Request $request, string $message, array $extra = []): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson() || $request->ajax()) {
            // The page reloads to show the new timeline entry; keep the toast for after.
            $request->session()->flash('status', $message);

            return response()->json(['status' => $message, 'reload' => true, ...$extra]);
        }

        return back()->with('status', $message);
    }

    protected function fail(Request $request, string $message): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['message' => $message], 422);
        }

        return back()->with('error', $message)->withInput();
    }
}
