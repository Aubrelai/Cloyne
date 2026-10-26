<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Setting;
use App\Services\Apify;
use App\Services\Claude;
use App\Services\EmailVerifier;
use App\Services\Instantly;
use App\Support\AutoUpdate;
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

    /** Which leads go to the CRM's call lists: a minimum score, plus packages that always go. */
    public function updateCrm(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('crm', [
            'crm_min_score' => ['required', 'integer', 'min:0', 'max:100'],
            'crm_always_packages' => ['nullable', 'array'],
            'crm_always_packages.*' => [Rule::in(array_keys(Business::packages()))],
        ]);
        $always = array_values(array_intersect(array_keys(Business::packages()), $data['crm_always_packages'] ?? []));

        Setting::put('crm_min_score', (int) $data['crm_min_score']);
        Setting::put('crm_always_packages', $always);

        $waiting = Business::forCrmCallList()->count();

        return redirect()->to(route('settings').'#crm')->with('status',
            'CRM call lists get leads scoring '.$data['crm_min_score'].'+'
            .($always ? ', plus every '.collect($always)->map(fn ($k) => Business::packages()[$k]['short'] ?? $k)->implode(', ').' lead' : '')
            .'. '.number_format($waiting).' '.str('lead')->plural($waiting).' waiting for the CRM to pick up.');
    }

    /** Owner: switch automatic updates on or off (the server script checks for the off file). */
    public function updateAutoUpdate(Request $request): RedirectResponse
    {
        $on = $request->boolean('on');
        AutoUpdate::turn($on);

        return redirect()->to(route('settings').'#updates')->with('status', $on
            ? 'Automatic updates are on. The server checks for a new version every 15 minutes.'
            : 'Automatic updates are off. Update by hand with: bash ~/cloyne-app/deploy/dreamhost.sh');
    }

    /** Owner: save the fact sheet. Blank rows are dropped; a row needs both a question and an answer. */
    public function updateFactsheet(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('factsheet', [
            'entries' => ['array', 'max:60'],
            'entries.*.q' => ['nullable', 'string', 'max:200'],
            'entries.*.a' => ['nullable', 'string', 'max:1200'],
        ]);

        $entries = collect($data['entries'] ?? [])
            ->map(fn ($e) => ['q' => trim((string) ($e['q'] ?? '')), 'a' => trim((string) ($e['a'] ?? ''))])
            ->reject(fn ($e) => $e['q'] === '' && $e['a'] === '')
            ->values();

        if ($entries->contains(fn ($e) => $e['q'] === '' || $e['a'] === '')) {
            return back()->withInput()->withErrors(['entries' => 'Every row needs both a question and an answer (or leave both blank to remove it).'], 'factsheet');
        }
        if ($entries->isEmpty()) {
            return back()->withErrors(['entries' => 'Keep at least one answer. To start over, use Reset to LaiQ defaults.'], 'factsheet');
        }

        Setting::put('factsheet', $entries->all());

        return redirect()->to(route('settings').'#factsheet')->with('status', 'Fact sheet saved: '.$entries->count().' '.str('answer')->plural($entries->count()).'.');
    }

    public function resetFactsheet(): RedirectResponse
    {
        Setting::forget('factsheet');
        config(['factsheet.entries' => (require config_path('factsheet.php'))['entries']]);

        return redirect()->to(route('settings').'#factsheet')->with('status', 'Fact sheet reset to LaiQ defaults.');
    }

    public function test(string $service, Apify $apify, Claude $claude, EmailVerifier $verifier): RedirectResponse
    {
        try {
            if ($service === 'instantly') {
                $inboxes = app(Instantly::class)->testConnection();

                return back()->with('status', 'Instantly works. '.$inboxes.' sending '.str('inbox')->plural($inboxes).' connected.');
            }
            if ($service === 'millionverifier') {
                $credits = $verifier->testConnection();

                return back()->with('status', 'MillionVerifier works. '.number_format($credits).' '.str('credit')->plural($credits).' left.');
            }
            if ($service === 'apify') {
                $name = $apify->testConnection();

                return back()->with('status', "Apify works. Signed in as {$name}.");
            }
            $claude->testConnection();

            return back()->with('status', 'Claude works. The key is valid.');
        } catch (Throwable $e) {
            return back()->withErrors(['connection' => FriendlyError::message($e, match ($service) { 'apify' => 'Apify', 'millionverifier' => 'MillionVerifier', 'instantly' => 'Instantly', default => 'Claude' })]);
        }
    }
}
