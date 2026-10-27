<?php

namespace App\Support;

use App\Models\ApiCost;
use App\Models\Audit;
use App\Models\Business;
use App\Models\Call;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * What's working, for one calendar month: the funnel from found to booked, results per caller
 * (from calls the CRM sends back) and per category, and what each booked call cost in Apify + Claude.
 */
class Results
{
    /** Call outcomes where someone actually spoke to the business. */
    public const TALKED = ['talked', 'call_back', 'not_interested', 'booked'];

    public static function month(CarbonInterface $month): array
    {
        $from = $month->copy()->startOfMonth();
        $to = $month->copy()->endOfMonth();
        $hot = (int) config('cloyne.outreach.min_score', 60);

        $calls = Call::whereBetween('called_at', [$from, $to]);
        $called = (clone $calls)->distinct()->count('business_id');
        $talked = (clone $calls)->whereIn('outcome', self::TALKED)->distinct()->count('business_id');
        $booked = Business::whereBetween('booked_at', [$from, $to])->count();

        $funnel = [
            ['key' => 'found', 'label' => 'Found', 'count' => Business::whereBetween('created_at', [$from, $to])->count()],
            ['key' => 'audited', 'label' => 'Audited', 'count' => Audit::whereBetween('audited_at', [$from, $to])->distinct()->count('business_id')],
            ['key' => 'hot', 'label' => "Hot ({$hot}+)", 'count' => Audit::whereBetween('audited_at', [$from, $to])->where('score', '>=', $hot)->distinct()->count('business_id')],
            ['key' => 'called', 'label' => 'Called', 'count' => $called],
            ['key' => 'talked', 'label' => 'Talked', 'count' => $talked],
            ['key' => 'booked', 'label' => 'Booked', 'count' => $booked],
            ['key' => 'won', 'label' => 'Won', 'count' => Business::where('deal_stage', 'won')->whereBetween('deal_updated_at', [$from, $to])->count()],
        ];

        $cost = ApiCost::monthTotals($month)['total'];

        return [
            'month' => $from->format('F Y'),
            'funnel' => $funnel,
            'callers' => self::callers($from, $to),
            'categories' => self::categories($from, $to, $hot),
            'cost' => $cost,
            'cost_per_booked' => $booked > 0 ? round($cost / $booked, 2) : null,
            'won_value' => (float) Business::where('deal_stage', 'won')->whereBetween('deal_updated_at', [$from, $to])->sum('deal_value'),
            'calls' => (clone $calls)->count(),
        ];
    }

    /** One row per caller: calls, conversations, bookings, booking rate. */
    private static function callers(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return Call::whereBetween('called_at', [$from, $to])->get(['caller', 'outcome', 'business_id'])
            ->groupBy(fn ($c) => trim((string) $c->caller) ?: 'Not named')
            ->map(fn (Collection $rows, string $caller) => [
                'caller' => $caller,
                'calls' => $rows->count(),
                'talked' => $rows->whereIn('outcome', self::TALKED)->count(),
                'booked' => $rows->where('outcome', 'booked')->unique('business_id')->count(),
            ])
            ->map(fn ($r) => $r + ['rate' => $r['calls'] ? (int) round($r['booked'] / $r['calls'] * 100) : 0])
            ->sortByDesc(fn ($r) => [$r['booked'], $r['calls']])
            ->values();
    }

    /** Categories with leads worked this month: hot leads, leads called, bookings. Top 8. */
    private static function categories(CarbonInterface $from, CarbonInterface $to, int $hot): Collection
    {
        $calls = Call::whereBetween('called_at', [$from, $to])->with('business:id,category')->get();
        $called = $calls->groupBy(fn ($c) => $c->business?->category)->map(fn ($g) => $g->unique('business_id')->count());
        $booked = $calls->where('outcome', 'booked')->groupBy(fn ($c) => $c->business?->category)->map(fn ($g) => $g->unique('business_id')->count());
        $leads = Business::whereBetween('created_at', [$from, $to])->selectRaw('category, count(*) as total, sum(case when score >= ? then 1 else 0 end) as hot', [$hot])
            ->groupBy('category')->get()->keyBy('category');

        return collect($leads->keys())->merge($called->keys())->filter()->unique()
            ->map(fn ($cat) => [
                'category' => $cat,
                'leads' => (int) ($leads[$cat]->total ?? 0),
                'hot' => (int) ($leads[$cat]->hot ?? 0),
                'called' => (int) ($called[$cat] ?? 0),
                'booked' => (int) ($booked[$cat] ?? 0),
            ])
            ->sortByDesc(fn ($r) => [$r['booked'], $r['called'], $r['hot'], $r['leads']])
            ->take(8)->values();
    }
}
