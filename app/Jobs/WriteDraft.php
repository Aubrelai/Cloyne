<?php

namespace App\Jobs;

use App\Models\Business;
use App\Models\Draft;
use App\Services\Drafter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/** Writes one outreach email in the background (the "Cloyne worker" window). */
class WriteDraft implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public int $backoff = 30;

    public function __construct(public Draft $draft) {}

    public function handle(Drafter $drafter): void
    {
        $drafter->write($this->draft->business, $this->draft);
    }

    public function failed(?Throwable $e): void
    {
        $this->draft->update(['status' => 'failed', 'error' => $e?->getMessage() ?? 'Writing failed']);
    }

    /** Start a draft for each business that doesn't already have one in play. */
    public static function queueFor(iterable $businesses): int
    {
        $count = 0;
        foreach ($businesses as $business) {
            /** @var Business $business */
            if ($business->drafts()->whereIn('status', Draft::ACTIVE)->exists()) {
                continue;
            }
            $draft = $business->drafts()->create(['status' => 'writing']);
            self::dispatch($draft);
            $count++;
        }

        return $count;
    }
}
