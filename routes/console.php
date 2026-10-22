<?php

use Illuminate\Support\Facades\Schedule;

/*
| Background work when Cloyne is online.
| The server runs "php artisan schedule:run" every minute (a cron job set up by
| deploy/dreamhost.sh). On your own computer, update.bat's worker window does this instead.
*/

// Work through queued audits and email drafts, then stop, so shared hosting never sees
// a process that runs forever. A new run starts the next minute.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=2 --timeout=150')
    ->everyMinute()
    ->withoutOverlapping(10)
    ->runInBackground();

// Pull in Lead Finder results even when nobody has the page open.
Schedule::command('cloyne:sync-searches')
    ->everyTwoMinutes()
    ->withoutOverlapping(10);
