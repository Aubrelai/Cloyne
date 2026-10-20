<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DraftController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\ScreenController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

/*
| Everything in Cloyne needs a login. Roles:
|   Viewer   – every page, no changes
|   Reviewer – also searches, audits, deletes (can:edit)
|   Owner    – also the team (can:manage-team)
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:20,1');
    Route::get('/setup', [AuthController::class, 'showSetup'])->name('setup');
    Route::post('/setup', [AuthController::class, 'setup'])->middleware('throttle:10,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/account', [AccountController::class, 'show'])->name('account');
    Route::put('/account/name', [AccountController::class, 'updateName'])->name('account.name');
    Route::put('/account/password', [AccountController::class, 'updatePassword'])->name('account.password');
});

Route::middleware(['auth', 'fresh-password'])->group(function () {
    Route::controller(ScreenController::class)->group(function () {
        Route::get('/', 'dashboard')->name('dashboard');
        Route::get('/lead-finder', 'finder')->name('finder');
        Route::get('/leads', 'leads')->name('leads');
        Route::get('/review', 'review')->name('review');
        Route::get('/campaigns', 'campaigns')->name('campaigns');
        Route::get('/inbox', 'inbox')->name('inbox');
        Route::get('/booked', 'booked')->name('booked');
        Route::get('/settings', 'settings')->name('settings');
    });

    // Checking a running search only pulls in its results, so viewers can do it too.
    Route::post('/lead-finder/check', [SearchController::class, 'check'])->name('searches.check');

    Route::middleware('can:edit')->group(function () {
        Route::post('/lead-finder/searches', [SearchController::class, 'store'])->name('searches.store');
        Route::delete('/lead-finder/searches/{search}', [SearchController::class, 'destroy'])->name('searches.destroy');
        Route::post('/leads/{business}/audit', [AuditController::class, 'one'])->name('audits.one');
        Route::post('/leads/audit', [AuditController::class, 'many'])->name('audits.many');
        Route::delete('/leads/{business}', [LeadController::class, 'destroy'])->name('leads.destroy');
        Route::post('/leads/delete', [LeadController::class, 'destroyMany'])->name('leads.destroy-many');
        Route::post('/leads/delete-matching', [LeadController::class, 'destroyMatching'])->name('leads.destroy-matching');

        // Review queue (Step 4). Nothing here sends email.
        Route::post('/leads/{business}/draft', [DraftController::class, 'write'])->name('drafts.write');
        Route::post('/review/write', [DraftController::class, 'writeMany'])->name('drafts.write-many');
        Route::put('/review/{draft}', [DraftController::class, 'update'])->name('drafts.update');
        Route::match(['post', 'put'], '/review/{draft}/approve', [DraftController::class, 'approve'])->name('drafts.approve');
        Route::post('/review/{draft}/skip', [DraftController::class, 'skip'])->name('drafts.skip');
        Route::post('/review/{draft}/rewrite', [DraftController::class, 'rewrite'])->name('drafts.rewrite');
        Route::post('/review/{draft}/reopen', [DraftController::class, 'reopen'])->name('drafts.reopen');
    });

    Route::middleware('can:manage-team')->prefix('/settings/team')->name('team.')->group(function () {
        Route::post('/', [TeamController::class, 'store'])->name('store');
        Route::put('/{user}', [TeamController::class, 'update'])->name('update');
        Route::post('/{user}/reset-password', [TeamController::class, 'resetPassword'])->name('reset');
        Route::delete('/{user}', [TeamController::class, 'destroy'])->name('destroy');
    });
});
