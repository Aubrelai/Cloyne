<?php

namespace App\Http\Controllers;

use App\Models\Search;
use App\Services\Apify;
use App\Services\LeadImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

/** Lead Finder: start Google Maps searches and pull in their results. */
class SearchController extends Controller
{
    public const MAX_RESULT_OPTIONS = [50, 100, 250, 500, 1000];

    public function store(Request $request, Apify $apify): RedirectResponse
    {
        $data = $request->validate([
            'category' => ['required', 'string', 'max:80'],
            'area' => ['required', 'string', 'max:120'],
            'max_results' => ['required', 'integer', 'in:' . implode(',', self::MAX_RESULT_OPTIONS)],
        ], [
            'category.required' => 'Type the kind of business to look for.',
            'area.required' => 'Type a city, county or state.',
        ]);

        if (! Apify::isConfigured()) {
            return back()->withInput()->withErrors(['apify' => 'Add your Apify key (APIFY_TOKEN) to the .env file first.']);
        }

        try {
            $run = $apify->startMapsSearch($data['category'], $data['area'], (int) $data['max_results']);
        } catch (Throwable $e) {
            return back()->withInput()->withErrors(['apify' => $e->getMessage()]);
        }

        Search::create($data + [
            'apify_run_id' => $run['run_id'],
            'apify_dataset_id' => $run['dataset_id'],
        ]);

        return redirect()->route('finder')
            ->with('status', "Searching Google Maps for {$data['category']} in {$data['area']}. This usually takes 1–5 minutes.");
    }

    /** "Check now" button: sync every running search. */
    public function check(Apify $apify, LeadImporter $importer): RedirectResponse
    {
        Search::where('status', 'running')->get()->each->sync($apify, $importer);

        return redirect()->route('finder');
    }
}
