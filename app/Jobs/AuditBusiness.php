<?php

namespace App\Jobs;

use App\Models\Business;
use App\Services\Auditor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/** Runs one AI audit in the background (the "Cloyne worker" window). */
class AuditBusiness implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 150;

    public int $backoff = 30;

    public function __construct(public Business $business) {}

    public function handle(Auditor $auditor): void
    {
        $auditor->audit($this->business);
    }

    public function failed(?Throwable $e): void
    {
        $this->business->update([
            'audit_status' => 'failed',
            'audit_error' => $e?->getMessage() ?? 'Audit failed',
        ]);
    }

    /** Queue audits for these businesses and mark them as queued. */
    public static function queueFor(iterable $businesses): int
    {
        $count = 0;
        foreach ($businesses as $business) {
            $business->update(['audit_status' => 'queued', 'audit_error' => null]);
            self::dispatch($business);
            $count++;
        }

        return $count;
    }
}
