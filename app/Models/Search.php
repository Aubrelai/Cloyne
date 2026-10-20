<?php

namespace App\Models;

use App\Services\Apify;
use App\Services\LeadImporter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Throwable;

/** One Lead Finder search: a Google Maps run on Apify and the businesses it found. */
class Search extends Model
{
    protected $guarded = ['id'];

    protected $attributes = ['status' => 'running'];

    protected function casts(): array
    {
        return ['finished_at' => 'datetime'];
    }

    public function businesses(): HasMany
    {
        return $this->hasMany(Business::class);
    }

    /** "Dentists · Dallas, TX" */
    public function label(): string
    {
        return "{$this->category} · {$this->area}";
    }

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    /**
     * Check Apify; if the run finished, import its businesses.
     * Safe to call any time: does nothing unless the search is still running.
     */
    public function sync(Apify $apify, LeadImporter $importer): void
    {
        if (! $this->isRunning() || ! $this->apify_run_id) {
            return;
        }

        try {
            $status = $apify->runStatus($this->apify_run_id);

            if (in_array($status, ['READY', 'RUNNING'], true)) {
                return;
            }

            if ($status !== 'SUCCEEDED') {
                $this->update([
                    'status' => 'failed',
                    'error' => 'The Google Maps search ended without finishing (' . strtolower($status) . '). Try again.',
                    'finished_at' => now(),
                ]);

                return;
            }

            $items = $apify->datasetItems($this->apify_dataset_id);
            $result = $importer->import($this, $items);

            $this->update([
                'status' => 'done',
                'found' => $result['found'],
                'added' => $result['added'],
                'error' => null,
                'finished_at' => now(),
            ]);
        } catch (Throwable $e) {
            // Network hiccup or Apify error: keep it running and show the reason; the next check retries.
            $this->update(['error' => $e->getMessage()]);
        }
    }
}
