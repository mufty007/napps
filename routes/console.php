<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled tasks (kernel)
|--------------------------------------------------------------------------
| Modules schedule their own tasks from their service provider.
*/

// No payment is lost if a callback or webhook never arrives.
Schedule::command('payments:reconcile')->everyFifteenMinutes()->withoutOverlapping()->onOneServer();
