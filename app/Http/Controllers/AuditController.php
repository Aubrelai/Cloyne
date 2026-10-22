<?php

namespace App\Http\Controllers;

use App\Jobs\AuditBusiness;
use App\Models\Business;
use App\Services\Auditor;
use App\Services\Claude;
use App\Support\FriendlyError;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

/** Start AI audits: one lead right now, or many in the background. */
class AuditController extends Controller
{
    /** Audit one lead immediately (takes ~10–20 seconds). */
    public function one(Business $business, Auditor $auditor): RedirectResponse
    {
        if (! Claude::isConfigured()) {
            return back()->withErrors(['audit' => 'Add your Claude API key (ANTHROPIC_API_KEY) to the .env file first.']);
        }

        try {
            $auditor->audit($business);
        } catch (Throwable $e) {
            $message = FriendlyError::message($e);
            $business->update(['audit_status' => 'failed', 'audit_error' => $message]);

            return back()->withErrors(['audit' => $message]);
        }

        return back()->with('status', "Audited {$business->name}.");
    }

    /** Queue audits for the ticked leads (re-audits too). */
    public function selected(Request $request): RedirectResponse
    {
        if (! Claude::isConfigured()) {
            return back()->withErrors(['audit' => 'Add your Claude API key (ANTHROPIC_API_KEY) to the .env file first.']);
        }
        $ids = $request->validate(['ids' => ['required', 'array', 'max:500'], 'ids.*' => ['integer']],
            ['ids.required' => 'Tick at least one lead first.'])['ids'];

        $count = AuditBusiness::queueFor(Business::whereIn('id', $ids)->where('stage', '!=', 'excluded')
            ->where(fn ($q) => $q->whereNull('audit_status')->orWhere('audit_status', '!=', 'queued'))->get());

        return back()->with('status', $count
            ? "Auditing {$count} " . str('lead')->plural($count) . ' in the background.'
            : 'Those leads are already being audited.');
    }

    /** Queue audits for every un-audited lead (optionally from one search), up to 500 at a time. */
    public function many(Request $request): RedirectResponse
    {
        if (! Claude::isConfigured()) {
            return back()->withErrors(['audit' => 'Add your Claude API key (ANTHROPIC_API_KEY) to the .env file first.']);
        }

        $businesses = Business::query()
            ->whereNull('score')
            ->where('stage', '!=', 'excluded')
            ->where(fn ($q) => $q->whereNull('audit_status')->orWhere('audit_status', 'failed'))
            ->when($request->integer('search'), fn ($q, $search) => $q->where('search_id', $search))
            ->limit(500)
            ->get();

        $count = AuditBusiness::queueFor($businesses);

        return back()->with('status', $count
            ? "Auditing {$count} " . str('lead')->plural($count) . ' in the background. Keep the Cloyne worker window open; refresh to see scores.'
            : 'Every lead here is already audited or queued.');
    }
}
