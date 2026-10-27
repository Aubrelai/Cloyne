<?php

namespace App\Http\Controllers;

use App\Jobs\HandleReply;
use App\Models\Draft;
use App\Models\Reply;
use App\Models\Setting;
use App\Services\Claude;
use App\Services\Instantly;
use App\Services\ReplyAgent;
use App\Support\FriendlyError;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

/** v3.0 Inbox actions: draft an answer, send it (after a person checks it), take it over, mark a call booked. */
class ReplyController extends Controller
{
    /** Owner: sort and draft answers automatically as replies arrive. */
    public function toggle(Request $request): RedirectResponse
    {
        $on = $request->boolean('on');
        Setting::put('reply_agent_on', $on);

        return back()->with('status', $on
            ? 'AI answers are on: new replies are sorted and an answer is drafted from the fact sheet. Nothing goes until someone approves it.'
            : 'AI answers are off. Replies still come in; answer them yourself.');
    }

    public function draft(Reply $reply): RedirectResponse
    {
        if (! Claude::isConfigured()) {
            return back()->withErrors(['reply' => 'Add your Claude API key first.']);
        }
        $reply->update(['answer_status' => null, 'answer_error' => null]);
        HandleReply::dispatch($reply);

        return back()->with('status', 'Drafting an answer from the fact sheet. Refresh in a minute.');
    }

    public function send(Request $request, Reply $reply, Instantly $instantly): RedirectResponse
    {
        $data = $request->validate(['answer' => ['required', 'string', 'max:3000']]);
        abort_if($reply->answer_status === 'sent', 409, 'Already sent.');

        if (! $reply->eaccount || ! Instantly::isConfigured()) {
            return back()->withErrors(['reply' => 'Can\'t send from Cloyne yet (Instantly not connected or the inbox is unknown). Answer it in Instantly instead.']);
        }

        $text = trim(str_replace("\r\n", "\n", $data['answer']))."\n\n".Draft::signature();
        $subject = str_starts_with(mb_strtolower((string) $reply->subject), 're:') ? $reply->subject : 'Re: '.$reply->subject;

        try {
            $instantly->replyTo($reply->instantly_email_id, $reply->eaccount, (string) $subject, $text);
        } catch (Throwable $e) {
            $reply->update(['answer' => $data['answer'], 'answer_error' => FriendlyError::message($e, 'Instantly')]);

            return back()->withErrors(['reply' => 'Not sent: '.FriendlyError::message($e, 'Instantly')]);
        }

        $reply->update(['answer' => $data['answer'], 'answer_status' => 'sent', 'answer_error' => null,
            'answered_by' => $request->user()->id, 'answered_at' => now(), 'read_at' => $reply->read_at ?? now()]);

        return back()->with('status', 'Answer sent to '.$reply->from_email.'.');
    }

    /** A person takes this one (answered elsewhere, or no answer needed). */
    public function handled(Request $request, Reply $reply): RedirectResponse
    {
        $reply->update(['answer_status' => 'handled', 'answered_by' => $request->user()->id, 'answered_at' => now(), 'read_at' => $reply->read_at ?? now()]);

        return back()->with('status', 'Marked as handled.');
    }

    /** They booked a call: the lead moves to Booked and goes to the CRM pipeline (v4.0). */
    public function booked(Reply $reply): RedirectResponse
    {
        abort_unless($reply->business, 404);
        $reply->business->update(['stage' => 'booked', 'exclude_reason' => null]);
        $reply->update(['category' => 'interested', 'read_at' => $reply->read_at ?? now()]);

        return back()->with('status', $reply->business->displayName().' marked as booked. It goes to the CRM pipeline.');
    }
}
