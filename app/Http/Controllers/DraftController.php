<?php

namespace App\Http\Controllers;

use App\Jobs\WriteDraft;
use App\Models\Business;
use App\Models\Draft;
use App\Services\Claude;
use App\Services\Drafter;
use App\Support\FriendlyError;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Review queue actions: write drafts, edit, approve, skip, rewrite.
 * Owners and reviewers only (can:edit). Nothing here sends email.
 */
class DraftController extends Controller
{
    /** Write one lead's email right now (~10 seconds), then open it in the Review queue. */
    public function write(Business $business, Drafter $drafter): RedirectResponse
    {
        if ($error = $this->cantWrite($business)) {
            return back()->withErrors(['draft' => $error]);
        }

        $existing = $business->drafts()->whereIn('status', Draft::ACTIVE)->latest()->first();
        if ($existing) {
            return redirect()->route('review', ['status' => $existing->status === 'approved' ? 'approved' : 'pending', 'draft' => $existing->id]);
        }

        try {
            $draft = $drafter->write($business);
        } catch (Throwable $e) {
            $business->drafts()->where('status', 'writing')->update(['status' => 'failed', 'error' => FriendlyError::message($e)]);

            return back()->withErrors(['draft' => FriendlyError::message($e)]);
        }

        return redirect()->route('review', ['draft' => $draft->id])->with('status', "Email written for {$business->name}.");
    }

    /** Queue emails for every hot lead that doesn't have one yet (up to 200 at a time). */
    public function writeMany(): RedirectResponse
    {
        if (! Claude::isConfigured()) {
            return back()->withErrors(['draft' => 'Add your Claude API key (ANTHROPIC_API_KEY) to the .env file first.']);
        }

        $count = WriteDraft::queueFor(Business::draftable()->orderByDesc('score')->limit(200)->get());

        return back()->with('status', $count
            ? "Writing {$count} ".str('email')->plural($count).' in the background. Keep the Cloyne worker window open; they appear here as they finish.'
            : 'Every hot lead with an email already has a draft.');
    }

    /** Queue emails for the ticked leads that qualify. */
    public function writeSelected(Request $request): RedirectResponse
    {
        if (! Claude::isConfigured()) {
            return back()->withErrors(['draft' => 'Add your Claude API key (ANTHROPIC_API_KEY) to the .env file first.']);
        }
        $ids = $request->validate(['ids' => ['required', 'array', 'max:500'], 'ids.*' => ['integer']],
            ['ids.required' => 'Tick at least one lead first.'])['ids'];

        $leads = Business::whereIn('id', $ids)
            ->whereHas('audits')
            ->whereHas('contacts', fn ($c) => $c->whereNotNull('email'))
            ->where('stage', '!=', 'excluded')
            ->get();
        $count = WriteDraft::queueFor($leads);
        $skipped = count($ids) - $count;

        return back()->with('status', ($count ? "Writing {$count} ".str('email')->plural($count).' in the background.' : 'No emails started.')
            .($skipped > 0 ? " {$skipped} skipped: not audited, no email, excluded, or already drafted." : ''));
    }

    public function update(Request $request, Draft $draft): RedirectResponse
    {
        $this->saveEdits($request, $draft);

        return back()->with('status', 'Draft saved.');
    }

    public function approve(Request $request, Draft $draft): RedirectResponse
    {
        abort_unless(in_array($draft->status, ['pending', 'approved'], true), 409, 'This draft can’t be approved.');
        $this->saveEdits($request, $draft);

        if (! $draft->to_email) {
            return back()->withErrors(['draft' => 'This lead has no email address, so the draft can’t be approved.']);
        }

        $draft->update(['status' => 'approved', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);

        return redirect()->route('review', ['draft' => $this->nextPending($draft)])
            ->with('status', "Approved the email to {$draft->business->displayName()}. It goes out with the next batch once sending is on (Campaigns).")
            ->with('undo', ['url' => route('drafts.reopen', $draft), 'label' => 'Undo']);
    }

    public function skip(Request $request, Draft $draft): RedirectResponse
    {
        $draft->update(['status' => 'skipped', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);

        if ($draft->business->stage === 'draft_ready'
            && ! $draft->business->drafts()->whereIn('status', Draft::ACTIVE)->exists()) {
            $draft->business->update(['stage' => 'audited']);
        }

        return redirect()->route('review', ['draft' => $this->nextPending($draft)])
            ->with('status', "Skipped {$draft->business->displayName()}. It won't be emailed.")
            ->with('undo', ['url' => route('drafts.reopen', $draft), 'label' => 'Undo']);
    }

    /** Throw away this version and have Claude write a new one now. */
    public function rewrite(Request $request, Draft $draft, Drafter $drafter): RedirectResponse
    {
        $style = $request->input('style');
        $style = array_key_exists((string) $style, Drafter::REWRITES) ? $style : null;

        abort_if($draft->status === 'approved', 409, 'Move it back to review before rewriting.');

        if ($error = $this->cantWrite($draft->business)) {
            return back()->withErrors(['draft' => $error]);
        }

        try {
            $drafter->write($draft->business, $draft, $style);
        } catch (Throwable $e) {
            $draft->update(['status' => 'failed', 'error' => FriendlyError::message($e)]);

            return back()->withErrors(['draft' => FriendlyError::message($e)]);
        }

        $what = ['shorter' => 'Shorter version', 'casual' => 'More casual version', 'angle' => 'New angle'][$style] ?? 'New version';

        return redirect()->route('review', ['draft' => $draft->id])->with('status', "{$what} written.");
    }

    /** Undo an approval or a skip: put the draft back in the queue. */
    public function reopen(Draft $draft): RedirectResponse
    {
        abort_unless(in_array($draft->status, ['approved', 'skipped'], true), 409);

        if ($draft->status === 'skipped' && $draft->business->drafts()->whereKeyNot($draft->id)->whereIn('status', Draft::ACTIVE)->exists()) {
            return back()->withErrors(['draft' => 'This lead already has another draft in the queue.']);
        }

        $draft->update(['status' => 'pending', 'reviewed_by' => null, 'reviewed_at' => null]);
        if ($draft->business->stage === 'audited') {
            $draft->business->update(['stage' => 'draft_ready']);
        }

        return redirect()->route('review', ['draft' => $draft->id])->with('status', 'Back in the review queue.');
    }

    private function saveEdits(Request $request, Draft $draft): void
    {
        if (! $request->hasAny(['subject', 'body'])) {
            return;
        }

        abort_unless(in_array($draft->status, ['pending', 'approved'], true), 409, 'This draft can’t be edited.');

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:5000'],
            'follow_up_1' => ['nullable', 'string', 'max:2000'],
            'follow_up_2' => ['nullable', 'string', 'max:2000'],
        ]);
        foreach (['body', 'follow_up_1', 'follow_up_2'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = filled($data[$field]) ? trim(str_replace("\r\n", "\n", $data[$field])) : null;
            }
        }
        $data = array_intersect_key($data, array_flip(array_merge(['subject', 'body'], array_keys($request->only(['follow_up_1', 'follow_up_2'])))));

        if (collect($data)->contains(fn ($value, $field) => $value !== $draft->{$field})) {
            $draft->update($data + ['edited' => true]);
        }
    }

    private function cantWrite(Business $business): ?string
    {
        return match (true) {
            ! Claude::isConfigured() => 'Add your Claude API key (ANTHROPIC_API_KEY) to the .env file first.',
            ! $business->latestAudit()->exists() => 'Audit this lead first, so the email can be about its real gaps.',
            $business->stage === 'excluded' => 'This lead is excluded.',
            default => null,
        };
    }

    private function nextPending(Draft $after): ?int
    {
        return Draft::status('pending')->whereKeyNot($after->id)
            ->join('businesses', 'businesses.id', '=', 'drafts.business_id')
            ->orderByDesc('businesses.score')->orderBy('drafts.id')
            ->value('drafts.id');
    }
}
