<?php

namespace App\Http\Controllers;

use App\Models\Search;
use App\Services\Apify;
use App\Services\LeadImporter;
use App\Services\LeadRemover;
use App\Support\FriendlyError;
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

        // Same search in the last 30 days? Ask before paying for it again.
        $recent = Search::recentMatch($data['category'], $data['area']);
        if ($recent && ! $request->boolean('repeat_ok')) {
            return back()->withInput()->with('repeat', [
                'label' => $recent->label(),
                'when' => $recent->created_at->diffForHumans(),
                'found' => $recent->found,
            ]);
        }

        return $this->start($data, $apify);
    }

    /** "Run again": the same search, started now. */
    public function again(Search $search, Apify $apify): RedirectResponse
    {
        return $this->start([
            'category' => $search->category,
            'area' => $search->area,
            'max_results' => $search->max_results,
        ], $apify);
    }

    private function start(array $data, Apify $apify): RedirectResponse
    {
        try {
            $run = $apify->startMapsSearch($data['category'], $data['area'], (int) $data['max_results']);
        } catch (Throwable $e) {
            return back()->withInput()->withErrors(['apify' => FriendlyError::message($e, 'Apify')]);
        }

        Search::create($data + [
            'apify_run_id' => $run['run_id'],
            'apify_dataset_id' => $run['dataset_id'],
        ]);

        return redirect()->route('finder')
            ->with('status', "Searching Google Maps for {$data['category']} in {$data['area']}. This usually takes 5–15 minutes.");
    }

    /** Delete a search and every lead it found. */
    public function destroy(Search $search, LeadRemover $remover): RedirectResponse
    {
        $label = $search->label();
        $count = $remover->removeSearch($search);

        return redirect()->route('finder')
            ->with('status', "Deleted the search {$label} and its {$count} " . str('lead')->plural($count) . '.');
    }

    /** "Check now" button: sync every running search. */
    public function check(Apify $apify, LeadImporter $importer): RedirectResponse
    {
        Search::where('status', 'running')->get()->each->sync($apify, $importer);

        return redirect()->route('finder');
    }
}
