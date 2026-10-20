<?php

use App\Http\Controllers\ScreenController;
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
