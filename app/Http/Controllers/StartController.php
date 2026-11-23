<?php

namespace App\Http\Controllers;

use App\Support\Navigation\Navigation;
use App\Support\Scope\ActiveContext;
use Illuminate\Http\RedirectResponse;

/**
 * Where people land after signing in or switching role: the first screen of
 * their navigation. For community members that is the feed (Home); parents
 * and people without a role land on their dashboard.
 */
class StartController extends Controller
{
    public function __invoke(ActiveContext $context, Navigation $navigation): RedirectResponse
    {
        $first = $navigation->for($context->role(), $context)->first();

        return redirect()->route($first->route ?? 'dashboard');
    }
}
