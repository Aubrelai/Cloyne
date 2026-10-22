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
        $ids = $this->ids($request);

        $count = app(LeadRemover::class)->remove(Business::whereIn('id', $ids)->get());

        return back()->with('status', "Deleted {$count} " . str('lead')->plural($count) . '.');
    }

    /** Set a lead aside with a reason instead of deleting it. It stays out of audits, emails and calls. */
    public function exclude(Request $request, Business $business): RedirectResponse
    {
        $reason = $this->reason($request);
        $business->exclude($reason);

        return back()
            ->with('status', "Excluded {$business->displayName()}: {$reason}.")
            ->with('undo', ['url' => route('leads.include', $business), 'label' => 'Undo']);
    }

    public function include(Business $business): RedirectResponse
    {
        $business->include();

        return back()->with('status', "{$business->displayName()} is back in your leads.");
    }

    public function excludeMany(Request $request): RedirectResponse
    {
        $ids = $this->ids($request);
        $reason = $this->reason($request);
        $leads = Business::whereIn('id', $ids)->get();
        $leads->each->exclude($reason);

        return back()->with('status', "Excluded {$leads->count()} ".str('lead')->plural($leads->count())." ({$reason}). Find them under Stage: Excluded.");
    }

    private function reason(Request $request): string
    {
        return $request->validate([
            'reason' => ['required', 'string', 'max:120'],
        ], ['reason.required' => 'Pick a reason for excluding.'])['reason'];
    }

    /** @return array<int, int> */
    private function ids(Request $request): array
    {
        return $request->validate([
            'ids' => ['required', 'array', 'max:500'],
            'ids.*' => ['integer'],
        ], ['ids.required' => 'Tick at least one lead first.'])['ids'];
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
