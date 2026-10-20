<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Business extends Model
{
    /** Pipeline stages, in order, with the label shown in the app. */
    public const STAGES = [
        'new' => 'New',
        'audited' => 'Audited',
        'draft_ready' => 'Draft ready',
        'emailed' => 'Emailed',
        'replied' => 'Replied',
        'booked' => 'Booked',
        'excluded' => 'Excluded',
    ];

    protected $guarded = ['id'];

    /** Defaults for a new business, so they're set before it's first saved. */
    protected $attributes = [
        'stage' => 'new',
        'source' => 'google_maps',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'decimal:1',
            'license_expires_on' => 'date',
        ];
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function audits(): HasMany
    {
        return $this->hasMany(Audit::class);
    }

    /** The first contact added is treated as the main one. */
    public function primaryContact(): HasOne
    {
        return $this->hasOne(Contact::class)->oldestOfMany();
    }

    public function latestAudit(): HasOne
    {
        return $this->hasOne(Audit::class)->latestOfMany('audited_at');
    }

    /**
     * Save a new audit and update the business's score and stage.
     *
     * @param  array<int, array{title: string, detail: string, passed: bool, points: int}>  $checks
     */
    public function recordAudit(array $checks, ?string $pitch = null): Audit
    {
        $score = min(100, (int) array_sum(array_column($checks, 'points')));

        $audit = $this->audits()->create([
            'checks' => $checks,
            'score' => $score,
            'pitch' => $pitch,
            'audited_at' => now(),
        ]);

        $this->score = $score;
        if ($this->stage === 'new') {
            $this->stage = 'audited';
        }
        $this->save();

        return $audit;
    }

    public function stageLabel(): string
    {
        return self::STAGES[$this->stage] ?? ucfirst($this->stage);
    }

    /** "Wellington, FL" */
    public function location(): string
    {
        return collect([$this->city, $this->state])->filter()->implode(', ');
    }

    /** Hot 60+, warm 40-59, cold under 40. */
    public function scopeScoreTier(Builder $query, ?string $tier): void
    {
        match ($tier) {
            'hot' => $query->where('score', '>=', 60),
            'warm' => $query->whereBetween('score', [40, 59]),
            'cold' => $query->where('score', '<', 40),
            default => null,
        };
    }
}
