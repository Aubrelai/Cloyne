<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Search;
use Illuminate\Support\Facades\DB;

/**
 * Deletes leads the same way everywhere:
 * - removes their contacts and audits,
 * - remembers their Google place IDs so later searches don't re-add them,
 * - removes finished searches that no longer have any leads.
 */
class LeadRemover
{
    /** @param  iterable<Business>  $businesses */
    public function remove(iterable $businesses): int
    {
        $count = 0;

        DB::transaction(function () use ($businesses, &$count) {
            foreach ($businesses as $business) {
                if ($business->place_id) {
                    DB::table('dismissed_places')->updateOrInsert(
                        ['place_id' => $business->place_id],
                        ['name' => $business->name, 'updated_at' => now(), 'created_at' => now()],
                    );
                }
                $business->delete(); // contacts and audits are removed with it
                $count++;
            }
        });

        $this->removeEmptySearches();

        return $count;
    }

    /** Delete a search and every lead it found. */
    public function removeSearch(Search $search): int
    {
        $count = 0;
        $search->businesses()->chunkById(200, function ($businesses) use (&$count) {
            $count += $this->remove($businesses);
        });
        $search->delete();

        return $count;
    }

    /** Finished searches whose leads have all been deleted. */
    private function removeEmptySearches(): void
    {
        Search::query()
            ->where('status', 'done')
            ->where('added', '>', 0)
            ->whereDoesntHave('businesses')
            ->delete();
    }
}
