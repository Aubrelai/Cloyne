<?php

namespace App\Console\Commands;

use App\Jobs\AuditBusiness;
use App\Models\Business;
use App\Models\Setting;
use App\Services\Claude;
use Illuminate\Console\Command;

/** Queue a fresh audit for leads whose last audit is old (config cloyne.reaudit_days), oldest first. */
class ReauditStale extends Command
{
    protected $signature = 'cloyne:reaudit-stale {--days= : Override cloyne.reaudit_days} {--limit= : Override cloyne.reaudit_per_day}';

    protected $description = 'Re-audit leads whose last audit is older than the set number of days';

    public function handle(): int
    {
        Setting::applyToConfig();
        $days = (int) ($this->option('days') ?? config('cloyne.reaudit_days', 60));
        $limit = (int) ($this->option('limit') ?? config('cloyne.reaudit_per_day', 100));

        if ($days <= 0 || $limit <= 0 || ! Claude::isConfigured()) {
            $this->info('Re-audits are off.');

            return self::SUCCESS;
        }

        $leads = Business::staleAudit($days)->limit($limit)->get();
        $count = AuditBusiness::queueFor($leads);
        $this->info("Queued {$count} ".str('re-audit')->plural($count).'.');

        return self::SUCCESS;
    }
}
