<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CostsController;
use App\Http\Controllers\CrmController;
use App\Http\Controllers\DraftController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\ScreenController;
use App\Http\Controllers\SavedViewController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SettingsController;
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

// The LaiQ Digital CRM's connection (costs for its Profit page, cold-call lists). Needs the key from Settings → CRM connection.
Route::middleware(['crm.key', 'throttle:120,1'])->prefix('/api')->group(function () {
    Route::get('/costs', [CostsController::class, 'show'])->name('api.costs');
    // Cold-call lists: the CRM sends its rows to be audited and reads back Starter / Growth / Scale,
    // pulls hot leads Cloyne found, and sends call results back. See App\Http\Controllers\CrmController.
    Route::post('/crm/leads', [CrmController::class, 'importLeads'])->name('api.crm.import');
    Route::get('/crm/leads', [CrmController::class, 'leads'])->name('api.crm.leads');
    Route::get('/crm/call-list', [CrmController::class, 'callList'])->name('api.crm.call-list');
    Route::post('/crm/call-list/added', [CrmController::class, 'callListAdded'])->name('api.crm.call-list.added');
    Route::post('/crm/calls', [CrmController::class, 'calls'])->name('api.crm.calls');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/account', [AccountController::class, 'show'])->name('account');
    Route::put('/account/name', [AccountController::class, 'updateName'])->name('account.name');
    Route::put('/account/password', [AccountController::class, 'updatePassword'])->name('account.password');
    Route::put('/account/theme', [AccountController::class, 'updateTheme'])->name('account.theme');
    Route::put('/account/preferences', [AccountController::class, 'updatePreferences'])->name('account.preferences');
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
        Route::get('/versions', 'versions')->name('versions');
    });

    // Saved views are personal, so every role can save their own.
    Route::post('/leads/views', [SavedViewController::class, 'store'])->name('views.store');
    Route::delete('/leads/views/{view}', [SavedViewController::class, 'destroy'])->name('views.destroy');

    // Checking a running search only pulls in its results, so viewers can do it too.
    Route::post('/lead-finder/check', [SearchController::class, 'check'])->name('searches.check');

    Route::middleware('can:edit')->group(function () {
        Route::post('/lead-finder/searches', [SearchController::class, 'store'])->name('searches.store');
        Route::delete('/lead-finder/searches/{search}', [SearchController::class, 'destroy'])->name('searches.destroy');
        Route::post('/lead-finder/searches/{search}/again', [SearchController::class, 'again'])->name('searches.again');
        Route::post('/leads/{business}/audit', [AuditController::class, 'one'])->name('audits.one');
        Route::post('/leads/audit', [AuditController::class, 'many'])->name('audits.many');
        Route::delete('/leads/{business}', [LeadController::class, 'destroy'])->name('leads.destroy');
        Route::post('/leads/delete', [LeadController::class, 'destroyMany'])->name('leads.destroy-many');
        Route::post('/leads/delete-matching', [LeadController::class, 'destroyMatching'])->name('leads.destroy-matching');
        Route::post('/leads/exclude', [LeadController::class, 'excludeMany'])->name('leads.exclude-many');
        Route::post('/leads/{business}/exclude', [LeadController::class, 'exclude'])->name('leads.exclude');
        Route::post('/leads/{business}/include', [LeadController::class, 'include'])->name('leads.include');
        Route::post('/leads/audit-selected', [AuditController::class, 'selected'])->name('audits.selected');
        Route::post('/leads/write-selected', [DraftController::class, 'writeSelected'])->name('drafts.write-selected');

        // Review queue (Step 4). Nothing here sends email.
        Route::post('/leads/{business}/draft', [DraftController::class, 'write'])->name('drafts.write');
        Route::post('/review/write', [DraftController::class, 'writeMany'])->name('drafts.write-many');
        Route::put('/review/{draft}', [DraftController::class, 'update'])->name('drafts.update');
        Route::match(['post', 'put'], '/review/{draft}/approve', [DraftController::class, 'approve'])->name('drafts.approve');
        Route::post('/review/{draft}/skip', [DraftController::class, 'skip'])->name('drafts.skip');
        Route::post('/review/{draft}/rewrite', [DraftController::class, 'rewrite'])->name('drafts.rewrite');
        Route::post('/review/{draft}/reopen', [DraftController::class, 'reopen'])->name('drafts.reopen');
    });

    Route::middleware('can:manage-team')->prefix('/settings')->group(function () {
        Route::put('/outreach', [SettingsController::class, 'updateOutreach'])->name('settings.outreach');
        Route::delete('/outreach', [SettingsController::class, 'resetOutreach'])->name('settings.outreach.reset');
        Route::put('/selling', [SettingsController::class, 'updateSelling'])->name('settings.selling');
        Route::post('/test/{service}', [SettingsController::class, 'test'])->whereIn('service', ['apify', 'claude'])->name('settings.test');
        Route::post('/costs-key', [CostsController::class, 'createKey'])->name('settings.costs-key');
        Route::delete('/costs-key', [CostsController::class, 'revokeKey'])->name('settings.costs-key.revoke');
    });

    Route::middleware('can:manage-team')->prefix('/settings/team')->name('team.')->group(function () {
        Route::post('/', [TeamController::class, 'store'])->name('store');
        Route::put('/{user}', [TeamController::class, 'update'])->name('update');
        Route::post('/{user}/reset-password', [TeamController::class, 'resetPassword'])->name('reset');
        Route::delete('/{user}', [TeamController::class, 'destroy'])->name('destroy');
    });
});
