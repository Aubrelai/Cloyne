<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    public function search(): BelongsTo
    {
        return $this->belongsTo(Search::class);
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
     * Save a new audit and update the business's score, packages and stage.
     *
     * $package      = the package the business fits best (may not be on sale yet)
     * $pitchPackage = a smaller package that also genuinely fits, to pitch while
     *                 the best fit isn't on sale (null if none does)
     *
     * @param  array<int, array{title: string, detail: string, passed: bool, points: int}>  $checks
     */
    public function recordAudit(array $checks, ?string $pitch = null, ?string $package = null, ?string $pitchPackage = null): Audit
    {
        $score = min(100, (int) array_sum(array_column($checks, 'points')));

        $audit = $this->audits()->create([
            'checks' => $checks,
            'score' => $score,
            'package' => $package,
            'pitch_package' => $pitchPackage,
            'pitch' => $pitch,
            'audited_at' => now(),
        ]);

        $this->score = $score;
        $this->package = $package;
        $this->pitch_package = $pitchPackage;
        if ($this->stage === 'new') {
            $this->stage = 'audited';
        }
        $this->save();

        return $audit;
    }

    /** All LaiQ packages from config/cloyne.php, keyed by their saved value. */
    public static function packages(): array
    {
        return config('cloyne.packages', []);
    }

    public function packageName(): ?string
    {
        return $this->package ? (self::packages()[$this->package]['name'] ?? $this->package) : null;
    }

    /** Short package name for tables, e.g. "Growth". */
    public function packageShort(): ?string
    {
        return $this->package ? (self::packages()[$this->package]['short'] ?? $this->package) : null;
    }

    /** Package keys LaiQ is selling right now (CLOYNE_SELLING in .env). */
    public static function sellingNow(): array
    {
        return config('cloyne.selling', []);
    }

    /**
     * The package to pitch today: the best fit if LaiQ is selling it, otherwise
     * the smaller package the audit said also fits (pitch_package), if that's on
     * sale. Null means "nothing to pitch yet" (e.g. fits Growth, has a good site).
     */
    public function pitchPackage(): ?string
    {
        $selling = self::sellingNow();

        if ($this->package && in_array($this->package, $selling, true)) {
            return $this->package;
        }
        if ($this->pitch_package && in_array($this->pitch_package, $selling, true)) {
            return $this->pitch_package;
        }

        return null;
    }

    public function pitchPackageShort(): ?string
    {
        $key = $this->pitchPackage();

        return $key ? (self::packages()[$key]['short'] ?? $key) : null;
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
