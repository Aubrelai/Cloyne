<?php

namespace App\Http\Controllers;

use App\Support\SampleData as Data;
use Illuminate\View\View;

/**
 * One method per Cloyne screen. Each passes sample data for now;
 * swap Data:: calls for real queries as each feature is built.
 */
class ScreenController extends Controller
{
    public function dashboard(): View
    {
        return view('screens.dashboard', [
            'stats' => Data::stats(),
            'metros' => Data::metros(),
            'dots' => Data::mapDots(),
            'labels' => Data::mapLabels(),
            'searches' => array_filter(Data::searches(), fn ($s) => $s['status'] === 'running'),
            'bookings' => array_slice(Data::bookings(), 0, 3),
        ]);
    }

    public function finder(): View
    {
        return view('screens.finder', ['searches' => Data::searches()]);
    }

    public function leads(): View
    {
        return view('screens.leads', ['leads' => Data::leads(), 'audit' => Data::audit()]);
    }

    public function review(): View
    {
        return view('screens.review', ['drafts' => Data::drafts()]);
    }

    public function campaigns(): View
    {
        return view('screens.campaigns', [
            'campaigns' => Data::campaigns(),
            'steps' => Data::steps(),
            'inboxes' => Data::inboxes(),
        ]);
    }

    public function inbox(): View
    {
        return view('screens.inbox', ['replies' => Data::replies(), 'filters' => Data::replyFilters()]);
    }

    public function booked(): View
    {
        return view('screens.booked', ['bookings' => Data::bookings(), 'funnel' => Data::funnel()]);
    }

    public function settings(): View
    {
        return view('screens.settings', ['inboxes' => Data::inboxes(), 'scoring' => Data::scoring()]);
    }
}
