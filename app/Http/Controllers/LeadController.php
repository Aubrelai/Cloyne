<?php

namespace App\Http\Controllers;

use App\Models\Business;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Deleting leads. Deleted Google places are remembered so searches don't re-add them. */
class LeadController extends Controller
{
    public function destroy(Business $business): RedirectResponse
    {
        $name = $business->name;
        $this->delete(collect([$business]));

        return redirect()->route('leads')->with('status', "Deleted {$name}.");
    }

    public function destroyMany(Request $request): RedirectResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array', 'max:500'],
            'ids.*' => ['integer'],
        ], ['ids.required' => 'Tick at least one lead to delete.'])['ids'];

        $businesses = Business::whereIn('id', $ids)->get();
        $this->delete($businesses);

        $count = $businesses->count();

        return back()->with('status', "Deleted {$count} " . str('lead')->plural($count) . '.');
    }

    /** Delete every lead matching the current filters (only when at least one filter is set). */
    public function destroyMatching(Request $request): RedirectResponse
    {
        $filters = array_filter($request->only(Business::FILTERS));
        if (! $filters) {
            return back()->withErrors(['ids' => 'Set a filter first. Cloyne won’t delete every lead at once.']);
        }

        $count = 0;
        Business::filtered($filters)->chunkById(200, function ($businesses) use (&$count) {
            $this->delete($businesses);
            $count += $businesses->count();
        });

        return redirect()->route('leads')->with('status', "Deleted {$count} " . str('lead')->plural($count) . '.');
    }

    /** @param  \Illuminate\Support\Collection<int, Business>  $businesses */
    private function delete($businesses): void
    {
        DB::transaction(function () use ($businesses) {
            foreach ($businesses as $business) {
                if ($business->place_id) {
                    DB::table('dismissed_places')->updateOrInsert(
                        ['place_id' => $business->place_id],
                        ['name' => $business->name, 'updated_at' => now(), 'created_at' => now()],
                    );
                }
                $business->delete(); // contacts and audits are removed with it
            }
        });
    }
}
