<?php

namespace App\Http\Controllers;

use App\Models\Reply;
use App\Services\Sender;
use App\Support\FriendlyError;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

/** Owner: switch sending on or off (version 2.0), and mark replies read. */
class CampaignController extends Controller
{
    public function start(Sender $sender): RedirectResponse
    {
        try {
            $sender->turnOn();
        } catch (Throwable $e) {
            return back()->withErrors(['sending' => FriendlyError::message($e, 'Instantly')]);
        }

        return back()->with('status', 'Sending is on. Approved emails go out on weekdays, within each inbox\'s daily limit.');
    }

    public function stop(Sender $sender): RedirectResponse
    {
        $sender->turnOff();

        return back()->with('status', 'Sending is paused. Nothing new goes out until you turn it back on.');
    }

    public function readReply(Request $request, Reply $reply): RedirectResponse
    {
        $reply->update(['read_at' => $reply->read_at ? null : now()]);

        return back();
    }
}
