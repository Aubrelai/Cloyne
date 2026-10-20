<?php

use App\Http\Controllers\AuditController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\ScreenController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

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

Route::post('/lead-finder/searches', [SearchController::class, 'store'])->name('searches.store');
Route::post('/lead-finder/check', [SearchController::class, 'check'])->name('searches.check');
Route::post('/leads/{business}/audit', [AuditController::class, 'one'])->name('audits.one');
Route::post('/leads/audit', [AuditController::class, 'many'])->name('audits.many');
Route::delete('/leads/{business}', [LeadController::class, 'destroy'])->name('leads.destroy');
Route::post('/leads/delete', [LeadController::class, 'destroyMany'])->name('leads.destroy-many');
