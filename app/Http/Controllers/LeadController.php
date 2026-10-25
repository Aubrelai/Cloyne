<?php

namespace App\Http\Controllers;

use App\Models\Business;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
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

    /** Download the leads matching the current Leads filters as a CSV file (opens in Excel or Google Sheets). */
    public function export(Request $request): StreamedResponse
    {
        $filters = $request->only(Business::FILTERS);
        $name = 'cloyne-leads-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($filters) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // so Excel reads accents and dashes correctly
            fputcsv($out, ['Business', 'Category', 'Phone', 'Email', 'Website', 'City', 'State', 'Rating', 'Reviews',
                'Score', 'Best fit', 'Pitch now', 'Talking point', 'Stage', 'CRM list', 'Added', 'Cloyne link']);

            Business::query()->filtered($filters)->with(['primaryContact', 'latestAudit'])
                ->orderByRaw('score is null')->orderByDesc('score')->orderBy('name')
                ->chunk(500, function ($leads) use ($out) {
                    foreach ($leads as $lead) {
                        fputcsv($out, array_map([self::class, 'safeCell'], [
                            $lead->displayName(), $lead->category, $lead->phoneDisplay(),
                            $lead->primaryContact?->email, $lead->website, $lead->city, $lead->state,
                            $lead->rating, $lead->review_count, $lead->score, $lead->packageShort(),
                            $lead->pitchPackageShort(), $lead->latestAudit?->pitch, $lead->stageLabel(),
                            $lead->crm_list, optional($lead->created_at)->format('Y-m-d'),
                            route('leads', ['lead' => $lead->id]),
                        ]));
                    }
                });
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Spreadsheets run cells that start with = + - @ as formulas; a leading apostrophe keeps them as text. */
    public static function safeCell(mixed $value): mixed
    {
        return is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
