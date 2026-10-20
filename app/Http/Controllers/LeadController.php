<?php

namespace App\Http\Controllers;

use App\Models\Business;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Services\LeadRemover;

/** Deleting leads. Deleted Google places are remembered so searches don't re-add them. */
class LeadController extends Controller
{
    public function destroy(Business $business): RedirectResponse
    {
        $name = $business->name;
        app(LeadRemover::class)->remove([$business]);

        return redirect()->route('leads')->with('status', "Deleted {$name}.");
    }

    public function destroyMany(Request $request): RedirectResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array', 'max:500'],
            'ids.*' => ['integer'],
        ], ['ids.required' => 'Tick at least one lead to delete.'])['ids'];

        $count = app(LeadRemover::class)->remove(Business::whereIn('id', $ids)->get());

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
            $count += app(LeadRemover::class)->remove($businesses);
        });

        return redirect()->route('leads')->with('status', "Deleted {$count} " . str('lead')->plural($count) . '.');
    }
}
