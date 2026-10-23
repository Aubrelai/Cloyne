<?php

namespace App\Models;

use App\Models\Search;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * What Cloyne spends on outside services.
 * Claude: every call is saved with its token counts and a dollar estimate (prices in config/services.php).
 * Apify: estimated from the businesses each search found (services.apify.price_per_1000).
 */
class ApiCost extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = ['amount' => 'float'];

    /** Save one Claude call. $usage is the "usage" block from the Messages API answer. Never breaks the caller. */
    public static function recordClaude(string $model, array $usage, string $purpose): void
    {
        try {
            $price = config("services.anthropic.prices.{$model}") ?? config('services.anthropic.prices.default');
            [$in, $out] = [(float) $price[0], (float) $price[1]];

            $tokens = [
                'input_tokens' => (int) ($usage['input_tokens'] ?? 0),
                'output_tokens' => (int) ($usage['output_tokens'] ?? 0),
                'cache_write_tokens' => (int) ($usage['cache_creation_input_tokens'] ?? 0),
                'cache_read_tokens' => (int) ($usage['cache_read_input_tokens'] ?? 0),
            ];

            // Cache writes cost 1.25x the input price, cache reads 0.1x.
            $amount = ($tokens['input_tokens'] * $in
                + $tokens['cache_write_tokens'] * $in * 1.25
                + $tokens['cache_read_tokens'] * $in * 0.1
                + $tokens['output_tokens'] * $out) / 1_000_000;

            static::create($tokens + ['service' => 'claude', 'purpose' => $purpose, 'model' => $model, 'amount' => round($amount, 6)]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Totals for one calendar month (in the app's time zone).
     *
     * @return array{month: string, total: float, items: list<array{key: string, name: string, amount: float, note: string, estimated: bool}>}
     */
    public static function monthTotals(CarbonInterface $month): array
    {
        $from = $month->copy()->startOfMonth();
        $to = $month->copy()->endOfMonth();

        $searches = Search::whereBetween('created_at', [$from, $to]);
        $searchCount = (clone $searches)->count();
        $found = (int) (clone $searches)->sum('found');
        $apify = round($found * (float) config('services.apify.price_per_1000', 4) / 1000, 2);

        $claude = static::where('service', 'claude')->whereBetween('created_at', [$from, $to]);
        $claudeAmount = round((float) (clone $claude)->sum('amount'), 2);
        $audits = (clone $claude)->where('purpose', 'audit')->count();
        $drafts = (clone $claude)->where('purpose', 'draft')->count();

        $items = [
            ['key' => 'apify', 'name' => 'Apify', 'amount' => $apify, 'estimated' => true,
                'note' => number_format($searchCount).' '.str('search')->plural($searchCount).' · '.number_format($found).' businesses'],
            ['key' => 'claude', 'name' => 'Claude API', 'amount' => $claudeAmount, 'estimated' => true,
                'note' => number_format($audits).' '.str('audit')->plural($audits).' · '.number_format($drafts).' '.str('email')->plural($drafts).' written'],
        ];

        return [
            'month' => $from->format('Y-m'),
            'total' => round($apify + $claudeAmount, 2),
            'items' => $items,
        ];
    }
}
