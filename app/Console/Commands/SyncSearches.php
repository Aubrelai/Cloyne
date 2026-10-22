<?php

namespace App\Console\Commands;

use App\Models\Search;
use App\Services\Apify;
use App\Services\LeadImporter;
use Illuminate\Console\Command;

/**
 * Check running Lead Finder searches and import any that finished.
 * Runs every two minutes from the scheduler, so results arrive even when
 * nobody has the Lead Finder page open.
 */
class SyncSearches extends Command
{
    protected $signature = 'cloyne:sync-searches';

    protected $description = 'Import results from Lead Finder searches that finished on Apify';

    public function handle(Apify $apify, LeadImporter $importer): int
    {
        if (! Apify::isConfigured()) {
            return self::SUCCESS;
        }

        $running = Search::where('status', 'running')->get();
        $running->each->sync($apify, $importer);
        $this->line("Checked {$running->count()} running ".str('search')->plural($running->count()).'.');

        return self::SUCCESS;
    }
}
