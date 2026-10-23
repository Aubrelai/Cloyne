<?php

namespace App\Http\Controllers;

use App\Models\ApiCost;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Cloyne's monthly Apify and Claude costs, for the LaiQ Digital CRM's Profit page.
 * The CRM calls GET /api/costs?month=2026-10 with "Authorization: Bearer <key>".
 * The Owner makes the key in Settings → Costs; only a hash of it is stored.
 */
class CostsController extends Controller
{
    private const KEY_SETTING = 'costs_key_hash';

    public function show(Request $request): JsonResponse
    {
        $hash = Setting::find(self::KEY_SETTING)?->value;
        $given = (string) ($request->bearerToken() ?: $request->header('X-Cloyne-Key'));

        if (! $hash || $given === '' || ! hash_equals(json_decode($hash, true), hash('sha256', $given))) {
            return response()->json(['error' => 'Wrong or missing key. Make one in Cloyne → Settings → Costs.'], 401);
        }

        $month = (string) $request->query('month', now()->format('Y-m'));
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            return response()->json(['error' => 'month must look like 2026-10.'], 422);
        }

        return response()->json(ApiCost::monthTotals(Carbon::createFromFormat('Y-m-d', "{$month}-01")) + [
            'currency' => 'USD',
            'source' => 'Cloyne',
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    /** Owner: make a new key (replaces the old one). Shown once. */
    public function createKey(): RedirectResponse
    {
        $key = 'cloyne_'.Str::random(40);
        Setting::put(self::KEY_SETTING, hash('sha256', $key));

        return redirect()->to(route('settings').'#costs')
            ->with('costs_key', $key)
            ->with('status', 'New CRM key made. Copy it now; Cloyne won’t show it again.');
    }

    public function revokeKey(): RedirectResponse
    {
        Setting::forget(self::KEY_SETTING);

        return redirect()->to(route('settings').'#costs')->with('status', 'CRM key turned off. The CRM can’t read costs until you make a new one.');
    }

    public static function hasKey(): bool
    {
        return Setting::isSet(self::KEY_SETTING);
    }
}
