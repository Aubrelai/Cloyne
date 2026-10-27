<?php

namespace App\Console\Commands;

use App\Jobs\WriteCallScript;
use App\Models\Business;
use App\Models\Setting;
use App\Services\Claude;
use Illuminate\Console\Command;

/** Queue call scripts for leads the CRM's callers will see that don't have one yet (oldest audit first). */
class WriteCallScripts extends Command
{
    protected $signature = 'cloyne:write-scripts {--limit=50}';

    protected $description = 'Write call scripts for CRM call-list leads that have none yet';

    public function handle(): int
    {
        Setting::applyToConfig();
        if (! config('services.anthropic.auto_script') || ! Claude::isConfigured()) {
            $this->info('Call scripts are off.');

            return self::SUCCESS;
        }

        $count = 0;
        Business::whereNull('call_script')->whereNotNull('phone')->whereNotNull('score')->where('stage', '!=', 'excluded')
            ->where(fn ($q) => $q->whereNotNull('crm_id')->orWhereIn('id', Business::forCrmCallList()->select('businesses.id')))
            ->orderBy('updated_at')->limit((int) $this->option('limit'))->get()
            ->each(function (Business $b) use (&$count) {
                WriteCallScript::dispatch($b);
                $count++;
            });

        $this->info("Queued {$count} call ".str('script')->plural($count).'.');

        return self::SUCCESS;
    }
}
