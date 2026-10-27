<?php

namespace App\Jobs;

use App\Models\Reply;
use App\Services\ReplyAgent;
use App\Support\FriendlyError;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/** Sorts one reply and drafts its answer in the background (v3.0). */
class HandleReply implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public int $backoff = 30;

    public function __construct(public Reply $reply) {}

    public function handle(ReplyAgent $agent): void
    {
        $agent->handle($this->reply);
    }

    public function failed(?Throwable $e): void
    {
        $this->reply->update(['answer_status' => 'failed', 'answer_error' => FriendlyError::message($e)]);
    }
}
