<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Setting;
use App\Services\Apify;
use App\Services\Claude;
use App\Support\FriendlyError;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Owner-only changes on the Settings screen. Saved values override
 * config/cloyne.php and .env until reset.
 */
class SettingsController extends Controller
{
    private const OUTREACH_KEYS = ['booking_url', 'mailing_address', 'tone', 'never', 'min_score', 'max_words'];

    public function updateOutreach(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('outreach', [
            'booking_url' => ['nullable', 'url:https,http', 'max:255'],
            'mailing_address' => ['nullable', 'string', 'max:255'],
            'tone' => ['required', 'string', 'max:500'],
            'never' => ['nullable', 'string', 'max:2000'],
            'min_score' => ['required', 'integer', 'between:30,95'],
            'max_words' => ['required', 'integer', 'between:40,250'],
        ], [
            'booking_url.url' => 'The booking link must be a full web address starting with https://.',
            'min_score.between' => 'The hot score must be between 30 and 95.',
            'max_words.between' => 'Email length must be between 40 and 250 words.',
        ]);

        $data['never'] = collect(preg_split('/\r?\n/', (string) ($data['never'] ?? '')))
            ->map(fn ($line) => trim($line, " \t-•"))->filter()->values()->all();
        $data['min_score'] = (int) $data['min_score'];
        $data['max_words'] = (int) $data['max_words'];

        foreach (self::OUTREACH_KEYS as $key) {
            Setting::put($key, $data[$key] ?? null);
        }

        return back()->with('status', 'Email rules saved. New drafts use them right away.');
    }

    public function resetOutreach(): RedirectResponse
    {
        foreach (self::OUTREACH_KEYS as $key) {
            Setting::forget($key);
        }

        return back()->with('status', 'Email rules reset to the defaults in config/cloyne.php and .env.');
    }

    public function updateSelling(Request $request): RedirectResponse
    {
        $ladder = array_keys(array_filter(Business::packages(), fn ($p) => $p['order'] !== null));
        $data = $request->validateWithBag('selling', [
            'selling' => ['nullable', 'array'],
            'selling.*' => [Rule::in($ladder)],
        ]);
        $selling = array_values(array_intersect($ladder, $data['selling'] ?? []));

        Setting::put('selling', $selling);

        return back()->with('status', $selling
            ? 'Packages on sale: '.collect($selling)->map(fn ($k) => Business::packages()[$k]['short'] ?? $k)->implode(', ').'.'
            : 'All packages paused. Cloyne still shows each lead\'s best fit but pitches nothing.');
    }

    public function test(string $service, Apify $apify, Claude $claude): RedirectResponse
    {
        try {
            if ($service === 'apify') {
                $name = $apify->testConnection();

                return back()->with('status', "Apify works. Signed in as {$name}.");
            }
            $claude->testConnection();

            return back()->with('status', 'Claude works. The key is valid.');
        } catch (Throwable $e) {
            return back()->withErrors(['connection' => FriendlyError::message($e, $service === 'apify' ? 'Apify' : 'Claude')]);
        }
    }
}
