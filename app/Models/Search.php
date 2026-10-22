<?php

namespace App\Models;

use App\Jobs\AuditBusiness;
use App\Services\Apify;
use App\Services\Claude;
use App\Services\LeadImporter;
use App\Support\FriendlyError;
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

    /** The same category + area searched in the last 30 days (not failed), if any. */
    public static function recentMatch(string $category, string $area): ?self
    {
        return static::query()
            ->whereRaw('lower(category) = ?', [mb_strtolower(trim($category))])
            ->whereRaw('lower(area) = ?', [mb_strtolower(trim($area))])
            ->where('status', '!=', 'failed')
            ->where('created_at', '>=', now()->subDays(30))
            ->latest()
            ->first();
    }

    /** Minutes since the search started. */
    public function minutesRunning(): int
    {
        return (int) $this->created_at->diffInMinutes(now());
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
                'skipped_existing' => $result['existing'],
                'skipped_dismissed' => $result['dismissed'],
                'skipped_closed' => $result['closed'],
                'error' => null,
                'finished_at' => now(),
            ]);

            if (config('services.anthropic.auto_audit') && Claude::isConfigured()) {
                AuditBusiness::queueFor($this->businesses()->whereNull('score')->get());
            }
        } catch (Throwable $e) {
            // Network hiccup or Apify error: keep it running and show the reason; the next check retries.
            $this->update(['error' => FriendlyError::message($e, 'Apify')]);
        }
    }
}
