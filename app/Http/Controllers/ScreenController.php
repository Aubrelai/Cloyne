<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Draft;
use App\Models\Search;
use App\Models\User;
use App\Services\Apify;
use App\Services\Claude;
use App\Services\LeadImporter;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * One method per Cloyne screen. Screens whose feature isn't built yet
 * show an empty state that says which step builds them.
 */
class ScreenController extends Controller
{
    public function dashboard(): View
    {
        $total = Business::count();

        // US map: place leads with coordinates on a simple lat/lng box (max 1,500 dots).
        $dots = Business::query()
            ->whereNotNull('latitude')->whereNotNull('longitude')
            ->whereBetween('latitude', [24, 50])->whereBetween('longitude', [-125, -66])
            ->latest()->limit(1500)
            ->get(['latitude', 'longitude', 'score'])
            ->map(fn ($b) => [
                'x' => round(((float) $b->longitude + 125) / 59 * 100, 2),
                'y' => round((50 - (float) $b->latitude) / 26 * 100, 2),
                'hot' => $b->score >= 60,
            ]);

        return view('screens.dashboard', [
            'total' => $total,
            'withEmail' => Business::whereHas('contacts', fn ($c) => $c->whereNotNull('email'))->count(),
            'audited' => Business::whereNotNull('score')->count(),
            'hot' => Business::where('score', '>=', 60)->count(),
            'booked' => Business::where('stage', 'booked')->count(),
            'newThisWeek' => Business::where('created_at', '>=', now()->subWeek())->count(),
            'notAudited' => Business::whereNull('score')->where('stage', '!=', 'excluded')->count(),
            'dots' => $dots,
            'cities' => Business::query()
                ->whereNotNull('city')
                ->selectRaw("city, state, count(*) as total")
                ->groupBy('city', 'state')
                ->orderByDesc('total')
                ->limit(6)
                ->get(),
            'searches' => Search::where('status', 'running')->latest()->get(),
            'recentSearches' => Search::where('status', 'done')->latest('finished_at')->limit(3)->get(),
            'apifyConnected' => Apify::isConfigured(),
            'claudeConnected' => Claude::isConfigured(),
            'queued' => Business::where('audit_status', 'queued')->count(),
        ]);
    }

    public function finder(Apify $apify, LeadImporter $importer): View
    {
        // Pull in results from any searches that finished since the last visit.
        if (Apify::isConfigured()) {
            Search::where('status', 'running')->get()->each->sync($apify, $importer);
        }

        return view('screens.finder', [
            'searches' => Search::withCount('businesses')->latest()->limit(50)->get(),
            'configured' => Apify::isConfigured(),
            'maxOptions' => SearchController::MAX_RESULT_OPTIONS,
        ]);
    }

    public function leads(Request $request): View
    {
        $filters = $request->only(Business::FILTERS);

        $leads = Business::query()
            ->with(['primaryContact', 'latestAudit'])
            ->filtered($filters)
            ->orderByRaw('score is null')   // unscored leads go last
            ->orderByDesc('score')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        // The lead shown in the side panel: ?lead=ID, else the top row.
        $selected = $request->filled('lead')
            ? Business::with(['primaryContact', 'latestAudit', 'latestDraft'])->find($request->integer('lead'))
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
            'matching' => array_filter($filters) ? Business::filtered($filters)->count() : 0,
            'fromSearch' => ! empty($filters['search']) ? Search::find($filters['search']) : null,
            'claudeConnected' => Claude::isConfigured(),
            'unaudited' => Business::whereNull('score')->where('stage', '!=', 'excluded')
                ->where(fn ($q) => $q->whereNull('audit_status')->orWhere('audit_status', 'failed'))
                ->when($filters['search'] ?? null, fn ($q, $search) => $q->where('search_id', $search))->count(),
            'queued' => Business::where('audit_status', 'queued')->count(),
        ]);
    }

    public function review(Request $request): View
    {
        $statuses = ['pending', 'approved', 'skipped', 'failed'];
        $status = in_array($request->query('status'), $statuses, true) ? $request->query('status') : 'pending';

        // Opening a specific draft shows the tab it lives in.
        $selected = $request->filled('draft')
            ? Draft::with(['business.latestAudit', 'contact', 'reviewer'])->find($request->integer('draft'))
            : null;
        if ($selected && in_array($selected->status, $statuses, true)) {
            $status = $selected->status;
        }

        $drafts = Draft::query()
            ->select('drafts.*')
            ->with('business')
            ->join('businesses', 'businesses.id', '=', 'drafts.business_id')
            ->where('drafts.status', $status)
            ->orderByDesc($status === 'pending' ? 'businesses.score' : 'drafts.updated_at')
            ->orderBy('drafts.id')
            ->paginate(50)
            ->withQueryString();

        if (! $selected || $selected->status !== $status) {
            $first = $drafts->first();
            $selected = $first ? Draft::with(['business.latestAudit', 'contact', 'reviewer'])->find($first->id) : null;
        }

        $counts = Draft::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('screens.review', [
            'status' => $status,
            'drafts' => $drafts,
            'selected' => $selected,
            'counts' => $counts,
            'writing' => (int) ($counts['writing'] ?? 0),
            'draftable' => Business::draftable()->count(),
            'claudeConnected' => Claude::isConfigured(),
            'readyToSend' => Draft::readyToSend(),
            'outreach' => config('cloyne.outreach'),
        ]);
    }

    public function campaigns(): View
    {
        return view('screens.campaigns');
    }

    public function inbox(): View
    {
        return view('screens.inbox');
    }

    public function booked(): View
    {
        return view('screens.booked', [
            'bookings' => Business::where('stage', 'booked')->with('primaryContact')->latest('updated_at')->get(),
        ]);
    }

    public function settings(): View
    {
        return view('screens.settings', [
            'apifyConnected' => Apify::isConfigured(),
            'searchCount' => Search::count(),
            'claudeConnected' => Claude::isConfigured(),
            'team' => User::orderByRaw("case role when 'owner' then 0 when 'reviewer' then 1 else 2 end")->orderBy('name')->get(),
        ]);
    }
}
