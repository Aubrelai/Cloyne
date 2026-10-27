<?php

namespace App\Http\Controllers;

use App\Models\Audit;
use App\Models\Business;
use App\Models\Contact;
use App\Models\Draft;
use App\Models\Search;
use App\Models\User;
use App\Services\Apify;
use App\Services\Claude;
use App\Services\LeadImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * One method per Cloyne screen. Screens whose feature isn't built yet
 * show an empty state that says which step builds them.
 */
class ScreenController extends Controller
{
    public function dashboard(Request $request): View
    {
        $total = Business::count();
        $weekAgo = now()->subWeek();
        $hotScore = (int) config('cloyne.outreach.min_score', 60);

        // Lead map: newest 1,500 leads that have coordinates, anywhere. Drawn on a real map by public/js/lead-map.js.
        $dots = Business::query()
            ->whereNotNull('latitude')->whereNotNull('longitude')
            ->where('stage', '!=', 'excluded')
            ->latest()->limit(1500)
            ->get(['id', 'name', 'category', 'city', 'latitude', 'longitude', 'score'])
            ->map(fn ($b) => [
                'id' => $b->id,
                'name' => $b->displayName(),
                'about' => collect([$b->category, $b->city])->filter()->implode(' · '),
                'score' => $b->score,
                'lat' => round((float) $b->latitude, 5),
                'lng' => round((float) $b->longitude, 5),
                'hot' => $b->score >= $hotScore,
                'url' => route('leads', ['lead' => $b->id]),
            ])->values();

        $withEmail = fn ($q) => $q->whereHas('contacts', fn ($c) => $c->whereNotNull('email'));
        $user = $request->user();
        $hour = (int) now($user->timezone ?: config('app.timezone'))->format('G');

        return view('screens.dashboard', [
            'greeting' => ($hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening')).', '.explode(' ', trim($user->name))[0],
            'today' => now($user->timezone ?: config('app.timezone'))->format('l, F j'),
            'total' => $total,
            'withEmail' => $withEmail(Business::query())->count(),
            'audited' => Business::whereNotNull('score')->count(),
            'hot' => Business::where('score', '>=', $hotScore)->count(),
            'booked' => Business::where('stage', 'booked')->count(),
            // "+38 this week" under each number.
            'week' => [
                'total' => Business::where('created_at', '>=', $weekAgo)->count(),
                'withEmail' => Contact::whereNotNull('email')->where('created_at', '>=', $weekAgo)->distinct()->count('business_id'),
                'audited' => Audit::where('audited_at', '>=', $weekAgo)->distinct()->count('business_id'),
                'hot' => Business::where('score', '>=', $hotScore)
                    ->whereHas('audits', fn ($a) => $a->where('audited_at', '>=', $weekAgo))->count(),
                'booked' => Business::where('stage', 'booked')->where('updated_at', '>=', $weekAgo)->count(),
            ],
            'notAudited' => Business::whereNull('score')->where('stage', '!=', 'excluded')->count(),
            'failedAudits' => Business::where('audit_status', 'failed')->whereNull('score')->count(),
            'failureReasons' => Business::auditFailureReasons(),
            'results' => \App\Support\Results::month(now()),
            'failedDrafts' => Draft::where('status', 'failed')->count(),
            'draftable' => Business::draftable()->count(),
            'dots' => $dots,
            'cities' => Business::query()
                ->whereNotNull('city')
                ->selectRaw('city, state, count(*) as total')
                ->groupBy('city', 'state')
                ->orderByDesc('total')
                ->limit(6)
                ->get(),
            'topCategories' => Business::query()
                ->where('score', '>=', $hotScore)
                ->selectRaw('category, count(*) as total')
                ->groupBy('category')
                ->orderByDesc('total')
                ->limit(5)
                ->get(),
            'searches' => Search::where('status', 'running')->latest()->get(),
            'recentSearches' => Search::where('status', 'done')->latest('finished_at')->limit(3)->get(),
            'apifyConnected' => Apify::isConfigured(),
            'claudeConnected' => Claude::isConfigured(),
            'queued' => Business::where('audit_status', 'queued')->count(),
            'hotScore' => $hotScore,
        ]);
    }

    public function finder(Apify $apify, LeadImporter $importer): View
    {
        // Pull in results from any searches that finished since the last visit.
        if (Apify::isConfigured()) {
            Search::where('status', 'running')->get()->each->sync($apify, $importer);
        }

        $hotScore = (int) config('cloyne.outreach.min_score', 60);

        return view('screens.finder', [
            'searches' => Search::withCount([
                'businesses',
                'businesses as audited_count' => fn ($q) => $q->whereNotNull('score'),
                'businesses as hot_count' => fn ($q) => $q->where('score', '>=', $hotScore),
            ])->latest()->limit(50)->get(),
            'recentAreas' => Search::query()->select('area')->groupBy('area')->orderByRaw('max(created_at) desc')->limit(12)->pluck('area'),
            'pricePerResult' => (float) config('services.apify.price_per_1000', 4) / 1000,
            'configured' => Apify::isConfigured(),
            'maxOptions' => SearchController::MAX_RESULT_OPTIONS,
        ]);
    }

    /** Leads table sorting: ?sort=score|name|reviews|newest&dir=asc|desc */
    public const SORTS = ['score', 'name', 'reviews', 'newest'];

    public const PER_PAGE = [25, 50, 100];

    /** Query keys that describe what the Leads list shows (remembered between visits). */
    private const LIST_KEYS = [...Business::FILTERS, 'sort', 'dir', 'per'];

    public function leads(Request $request): View|RedirectResponse
    {
        // Remember the last filters: a plain visit to /leads reopens them. "Clear" (?clear=1) forgets them.
        if ($request->boolean('clear')) {
            $request->session()->forget('leads.query');

            return redirect()->route('leads');
        }
        $listQuery = array_filter($request->only(self::LIST_KEYS), fn ($v) => $v !== null && $v !== '');
        $untouched = ! $request->hasAny([...self::LIST_KEYS, 'lead', 'page', 'view', 'focus']);
        if ($untouched && ($saved = $request->session()->get('leads.query'))) {
            return redirect()->route('leads', $saved);
        }
        if (! $untouched) {
            $request->session()->put('leads.query', $listQuery);
        }

        $filters = $request->only(Business::FILTERS);
        $sort = in_array($request->query('sort'), self::SORTS, true) ? $request->query('sort') : 'score';
        $dir = $request->query('dir') === 'asc' ? 'asc' : 'desc';
        $per = in_array($request->integer('per'), self::PER_PAGE, true) ? $request->integer('per') : 25;

        $query = Business::query()->with(['primaryContact', 'latestAudit'])->filtered($filters);
        match ($sort) {
            'name' => $query->orderBy('name', $dir === 'desc' ? 'desc' : 'asc'),
            'reviews' => $query->orderByRaw('review_count is null')->orderBy('review_count', $dir)->orderByDesc('rating'),
            'newest' => $query->orderBy('created_at', $dir)->orderBy('id', $dir),
            default => $query->orderByRaw('score is null')->orderBy('score', $dir)->orderBy('name'),
        };
        $leads = $query->paginate($per)->withQueryString();

        // The lead shown in the side panel: ?lead=ID, else the top row.
        $selectedId = $request->filled('lead') ? $request->integer('lead') : $leads->first()?->id;
        $selected = $selectedId
            ? Business::with(['primaryContact', 'latestAudit', 'latestDraft', 'contacts'])->find($selectedId)
            : null;

        $hotScore = (int) config('cloyne.outreach.min_score', 60);

        return view('screens.leads', [
            'leads' => $leads,
            'selected' => $selected,
            'filters' => $filters,
            'sort' => $sort,
            'dir' => $dir,
            'per' => $per,
            'total' => Business::where('stage', '!=', 'excluded')->count(),
            'excludedCount' => Business::where('stage', 'excluded')->count(),
            'hotCount' => Business::where('score', '>=', $hotScore)->where('stage', '!=', 'excluded')->count(),
            'hotScore' => $hotScore,
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
            'views' => $request->user()->savedViews()->orderBy('name')->get(),
            'activeView' => $request->integer('view') ?: null,
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
            'reviewedToday' => Draft::where('reviewed_by', $request->user()->id)
                ->where('reviewed_at', '>=', now($request->user()->timezone ?: config('app.timezone'))->startOfDay()->utc())
                ->count(),
        ]);
    }

    public function campaigns(\App\Services\Sender $sender): View
    {
        $accounts = \App\Services\Instantly::isConfigured()
            ? \Illuminate\Support\Facades\Cache::remember('instantly.accounts', 120, fn () => rescue(fn () => app(\App\Services\Instantly::class)->accounts(), null, false))
            : null;
        $weekAgo = now()->subDays(7);

        return view('screens.campaigns', [
            'readiness' => $sender->readiness($accounts),
            'ready' => $sender->isReady($accounts),
            'on' => \App\Services\Sender::isOn(),
            'pausedReason' => config('cloyne.settings.sending_paused_reason'),
            'accounts' => $accounts ?? [],
            'limitToday' => $accounts ? collect($accounts)->where('sending', true)->count() * (int) config('services.instantly.per_inbox_daily', 30) : 0,
            'remainingToday' => $accounts ? $sender->remainingToday($accounts) : 0,
            'waiting' => Draft::where('status', 'approved')->whereNull('send_status')->count(),
            'health' => \App\Services\Sender::health(),
            'week' => [
                'sent' => Draft::where('queued_at', '>=', $weekAgo)->count(),
                'replied' => Draft::where('queued_at', '>=', $weekAgo)->where('send_status', 'replied')->count(),
            ],
            'recent' => Draft::whereNotNull('send_status')->with('business')->latest('queued_at')->limit(25)->get(),
        ]);
    }

    public function inbox(Request $request): View
    {
        $show = in_array($request->query('show'), ['all', 'todo'], true) ? $request->query('show') : 'people';

        return view('screens.inbox', [
            'show' => $show,
            'replies' => \App\Models\Reply::with(['business', 'answeredBy'])
                ->when($show !== 'all', fn ($q) => $q->where('is_auto_reply', false))
                ->when($show === 'todo', fn ($q) => $q->where(fn ($q) => $q->whereNull('answer_status')->orWhereIn('answer_status', ['drafted', 'failed']))->where('opt_out', false))
                ->latest('received_at')->paginate(30)->withQueryString(),
            'unread' => \App\Models\Reply::whereNull('read_at')->where('is_auto_reply', false)->count(),
            'sendingOn' => \App\Services\Sender::isOn(),
            'agentOn' => \App\Services\ReplyAgent::isOn(),
            'canSend' => \App\Services\Instantly::isConfigured(),
        ]);
    }

    public function booked(): View
    {
        return view('screens.booked', [
            'bookings' => Business::where(fn ($q) => $q->whereIn('stage', ['booked', 'won', 'lost'])->orWhereNotNull('booked_at'))
                ->with('primaryContact')->orderByRaw('coalesce(booked_at, updated_at) desc')->get(),
            'wonThisMonth' => Business::where('deal_stage', 'won')->where('deal_updated_at', '>=', now()->startOfMonth()),
        ]);
    }

    public function settings(): View
    {
        return view('screens.settings', [
            'apifyConnected' => Apify::isConfigured(),
            'searchCount' => Search::count(),
            'claudeConnected' => Claude::isConfigured(),
            'verifierConnected' => \App\Services\EmailVerifier::isConfigured(),
            'usage' => [
                'checked' => \App\Models\Contact::where('email_checked_at', '>=', now()->startOfMonth())->count(),
                'invalid' => \App\Models\Contact::where('email_checked_at', '>=', now()->startOfMonth())->where('email_status', 'invalid')->count(),
                'searches' => Search::where('created_at', '>=', now()->startOfMonth())->count(),
                'found' => (int) Search::where('created_at', '>=', now()->startOfMonth())->sum('found'),
                'audits' => Audit::where('audited_at', '>=', now()->startOfMonth())->count(),
                'drafts' => Draft::where('created_at', '>=', now()->startOfMonth())->whereNotNull('body')->count(),
                'apifyCost' => (int) Search::where('created_at', '>=', now()->startOfMonth())->sum('found') * (float) config('services.apify.price_per_1000', 4) / 1000,
            ],
            'costs' => \App\Models\ApiCost::monthTotals(now()),
            'costsKey' => CostsController::hasKey(),
            'autoUpdate' => ['on' => \App\Support\AutoUpdate::isOn(), 'status' => \App\Support\AutoUpdate::status()],
            'crmStats' => [
                'linked' => Business::whereNotNull('crm_id')->count(),
                'audited' => Business::whereNotNull('crm_id')->whereNotNull('score')->count(),
                'calls' => \App\Models\Call::count(),
                'waiting' => Business::forCrmCallList()->count(),
            ],
            'outreachCustom' => \App\Models\Setting::isSet('tone') || \App\Models\Setting::isSet('booking_url'),
            'team' => User::orderByRaw("case role when 'owner' then 0 when 'reviewer' then 1 else 2 end")->orderBy('name')->get(),
        ]);
    }

    /** What's new: every Cloyne version, newest first. */
    public function versions(): View
    {
        $versions = collect(config('versions', []))
            ->sort(fn ($a, $b) => version_compare($b['version'], $a['version']))
            ->values();

        return view('screens.versions', ['versions' => $versions]);
    }
}
