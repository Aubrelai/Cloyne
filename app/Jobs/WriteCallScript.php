<?php

namespace App\Jobs;

use App\Models\Business;
use App\Services\CallScripter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Writes one call script in the background (v2.1). */
class WriteCallScript implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public int $backoff = 30;

    public function __construct(public Business $business) {}

    public function handle(CallScripter $scripter): void
    {
        $scripter->write($this->business);
    }

    /** Leads callers will see: on a CRM call list already, or about to go to one (Settings → CRM connection rules). */
    public static function wanted(Business $business): bool
    {
        return filled($business->phone) && ! is_null($business->score) && $business->stage !== 'excluded'
            && (filled($business->crm_id) || Business::whereKey($business->id)->forCrmCallList()->exists());
    }
}
