<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Support\SampleData as Data;
use Illuminate\Http\Request;
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

    public function leads(Request $request): View
    {
        $filters = $request->only(['q', 'category', 'package', 'state', 'score', 'email']);

        $leads = Business::query()
            ->with(['primaryContact', 'latestAudit'])
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhereHas('contacts', fn ($c) => $c->where('email', 'like', "%{$term}%"))))
            ->when($filters['category'] ?? null, fn ($q, $category) => $q->where('category', $category))
            ->when($filters['package'] ?? null, fn ($q, $package) => $q->where('package', $package))
            ->when($filters['state'] ?? null, fn ($q, $state) => $q->where('state', $state))
            ->scoreTier($filters['score'] ?? null)
            ->when($filters['email'] ?? null, fn ($q, $status) => $status === 'none'
                ? $q->whereDoesntHave('contacts', fn ($c) => $c->whereNotNull('email'))
                : $q->whereHas('contacts', fn ($c) => $c->where('email_status', $status)))
            ->orderByRaw('score is null')   // unscored leads go last
            ->orderByDesc('score')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        // The lead shown in the side panel: ?lead=ID, else the top row.
        $selected = $request->filled('lead')
            ? Business::with(['primaryContact', 'latestAudit'])->find($request->integer('lead'))
            : $leads->first();

        return view('screens.leads', [
            'leads' => $leads,
            'selected' => $selected,
            'filters' => $filters,
            'total' => Business::count(),
            'hotCount' => Business::where('score', '>=', 60)->count(),
            'categories' => Business::query()->distinct()->orderBy('category')->pluck('category'),
            'packages' => Business::packages(),
            'states' => Business::query()->whereNotNull('state')->distinct()->orderBy('state')->pluck('state'),
        ]);
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
