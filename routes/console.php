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

// Websites and ads change: re-audit leads whose last audit is older than cloyne.reaudit_days (60),
// at most cloyne.reaudit_per_day (100) a night, oldest first.
Schedule::command('cloyne:reaudit-stale')
    ->dailyAt('03:30')
    ->withoutOverlapping(30);
