<?php

namespace App\Models;

use App\Support\BusinessName;
use App\Support\Phone;
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

    /** Reasons offered when excluding a lead instead of deleting it. */
    public const EXCLUDE_REASONS = [
        'Not a fit',
        'Chain or franchise',
        'Already a client',
        'Asked not to be contacted',
        'Closed or moved',
        'Duplicate',
        'Not interested',
    ];

    /** Websites many businesses share, so they never mean "same business" or "chain". */
    public const SHARED_HOSTS = ['facebook.com', 'm.facebook.com', 'instagram.com', 'business.site', 'linktr.ee',
        'yelp.com', 'google.com', 'sites.google.com', 'wixsite.com', 'square.site', 'godaddysites.com', 'yellowpages.com',
        'nextdoor.com', 'tiktok.com', 'linkedin.com', 'x.com', 'twitter.com', 'youtube.com', 'squarespace.com', 'weebly.com'];

    /** A website shared by this many listings marks them all as a chain. */
    public const CHAIN_LOCATIONS = 4;

    protected $guarded = ['id'];

    /** Defaults for a new business, so they're set before it's first saved. */
    protected $attributes = [
        'stage' => 'new',
        'source' => 'google_maps',
        'is_chain' => false,
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'decimal:1',
            'license_expires_on' => 'date',
            'is_chain' => 'boolean',
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

    public function calls(): HasMany
    {
        return $this->hasMany(Call::class)->latest('called_at');
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

    public function drafts(): HasMany
    {
        return $this->hasMany(Draft::class);
    }

    public function latestDraft(): HasOne
    {
        return $this->hasOne(Draft::class)->latestOfMany();
    }

    /**
     * Leads that should get a first email: hot, has an email, has a package
     * LaiQ is selling, not excluded/contacted, and no draft already in play.
     */
    /**
     * Audited leads with a phone that Cloyne offers to the CRM's call lists: score at least
     * cloyne.crm.min_score, or best-fit package in cloyne.crm.always_packages. Not in the CRM yet.
     */
    public function scopeForCrmCallList(Builder $query): Builder
    {
        $minScore = (int) config('cloyne.crm.min_score', 60);
        $always = array_values((array) config('cloyne.crm.always_packages', []));

        return $query
            ->whereNull('crm_id')
            ->whereNotNull('phone')
            ->whereNotNull('score')
            ->whereNotIn('stage', ['excluded', 'booked', 'emailed', 'replied'])
            ->where(fn ($q) => $q->where('score', '>=', $minScore)
                ->when($always, fn ($q) => $q->orWhereIn('package', $always)));
    }

    public function scopeDraftable(Builder $query): Builder
    {
        $selling = self::sellingNow();

        return $query
            ->where('score', '>=', (int) config('cloyne.outreach.min_score', 60))
            ->whereIn('stage', ['audited', 'draft_ready', 'new'])
            ->whereHas('contacts', fn ($c) => $c->whereNotNull('email')->whereNotIn('email_status', ['invalid', 'bounced']))
            ->where(fn ($q) => $q->whereIn('package', $selling)->orWhereIn('pitch_package', $selling))
            ->whereDoesntHave('drafts', fn ($d) => $d->whereIn('status', Draft::ACTIVE))
            // Leads on the CRM's cold-call lists are the callers' to work, so Cloyne doesn't email them too.
            ->whereNull('crm_id');
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

    /** The name as a person would write it ("Bright Smile Dental", not "BRIGHT SMILE DENTAL LLC"). */
    public function displayName(): string
    {
        return BusinessName::clean($this->name) ?: (string) $this->name;
    }

    /** "(305) 555-0100" */
    public function phoneDisplay(): ?string
    {
        return Phone::display($this->phone);
    }

    /** Google Maps page for this business. */
    public function mapsUrl(): ?string
    {
        if ($this->place_id) {
            return 'https://www.google.com/maps/place/?q=place_id:'.urlencode($this->place_id);
        }

        $query = trim($this->name.' '.$this->location());

        return $query ? 'https://www.google.com/maps/search/?api=1&query='.urlencode($query) : null;
    }

    /** Mark every listing whose website is shared by CHAIN_LOCATIONS or more listings. */
    public static function flagChains(array $hosts): void
    {
        $hosts = array_values(array_diff(array_unique(array_filter($hosts)), self::SHARED_HOSTS));
        if (! $hosts) {
            return;
        }

        $chains = static::query()->whereIn('website_host', $hosts)
            ->groupBy('website_host')->havingRaw('count(*) >= ?', [self::CHAIN_LOCATIONS])
            ->pluck('website_host');

        if ($chains->isNotEmpty()) {
            static::whereIn('website_host', $chains)->update(['is_chain' => true]);
        }
    }

    /** Set aside a lead without deleting it: it stays in Cloyne, but gets no audits, emails or calls. */
    public function exclude(string $reason): void
    {
        $this->update(['stage' => 'excluded', 'exclude_reason' => $reason]);
        $this->drafts()->whereIn('status', ['writing', 'pending'])->update(['status' => 'skipped', 'reviewed_at' => now()]);
    }

    /** Undo an exclude: back to Audited (or New if never audited). */
    public function include(): void
    {
        $this->update(['stage' => is_null($this->score) ? 'new' : 'audited', 'exclude_reason' => null]);
    }

    /** Days since the latest audit, or null. */
    /** Audited leads still in play whose newest audit is older than $days days, oldest audit first. */
    public function scopeStaleAudit(Builder $query, int $days): Builder
    {
        $latest = Audit::selectRaw('business_id, max(audited_at) as last_audited_at')->groupBy('business_id');

        return $query->select('businesses.*')
            ->joinSub($latest, 'last_audit', 'last_audit.business_id', '=', 'businesses.id')
            ->where('last_audit.last_audited_at', '<', now()->subDays($days))
            ->whereNotNull('businesses.score')
            ->whereNotIn('businesses.stage', ['excluded', 'booked'])
            ->where(fn ($q) => $q->whereNull('businesses.audit_status')->orWhere('businesses.audit_status', '!=', 'queued'))
            ->orderBy('last_audit.last_audited_at');
    }

    public function auditAgeDays(): ?int
    {
        $at = $this->latestAudit?->audited_at;

        return $at ? (int) $at->diffInDays(now()) : null;
    }

    /** "Wellington, FL" */
    public function location(): string
    {
        return collect([$this->city, $this->state])->filter()->implode(', ');
    }

    /** Query-string filters the Leads page understands. */
    public const FILTERS = ['q', 'search', 'category', 'package', 'pitchable', 'state', 'score', 'email', 'audited', 'stage', 'chain', 'from'];

    /** Apply the Leads page filters (used for the list and for "delete all matching"). */
    public function scopeFiltered(Builder $query, array $filters): void
    {
        $query
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhereHas('contacts', fn ($c) => $c->where('email', 'like', "%{$term}%"))))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where('search_id', $search))
            ->when($filters['category'] ?? null, fn ($q, $category) => $q->where('category', $category))
            ->when($filters['package'] ?? null, fn ($q, $package) => $q->where('package', $package))
            ->when($filters['pitchable'] ?? null, fn ($q) => $q->where(fn ($w) => $w
                ->whereIn('package', self::sellingNow())
                ->orWhereIn('pitch_package', self::sellingNow())))
            ->when($filters['state'] ?? null, fn ($q, $state) => $q->where('state', $state))
            ->scoreTier($filters['score'] ?? null)
            ->when($filters['email'] ?? null, fn ($q, $status) => match ($status) {
                'none' => $q->whereDoesntHave('contacts', fn ($c) => $c->whereNotNull('email')),
                'any' => $q->whereHas('contacts', fn ($c) => $c->whereNotNull('email')),
                default => $q->whereHas('contacts', fn ($c) => $c->where('email_status', $status)),
            })
            ->when($filters['audited'] ?? null, fn ($q, $audited) => match ($audited) {
                'no' => $q->whereNull('score'),
                'failed' => $q->whereNull('score')->where('audit_status', 'failed'),
                default => $q->whereNotNull('score'),
            })
            ->when($filters['from'] ?? null, fn ($q, $from) => match ($from) {
                'crm' => $q->whereNotNull('crm_id'),
                'finder' => $q->whereNull('crm_id'),
                default => null,
            })
            ->when($filters['stage'] ?? null, fn ($q, $stage) => $q->where('stage', $stage))
            // Excluded leads stay out of the list unless you ask for them.
            ->when(($filters['stage'] ?? null) === null, fn ($q) => $q->where('stage', '!=', 'excluded'))
            ->when($filters['chain'] ?? null, fn ($q, $chain) => $q->where('is_chain', $chain === 'yes'));
    }

    /**
     * Why audits failed, most common first: [['reason' => 'Claude rejected the API key…', 'count' => 236], …].
     *
     * @return list<array{reason: string, count: int}>
     */
    public static function auditFailureReasons(int $limit = 3): array
    {
        return static::whereNull('score')->where('audit_status', 'failed')
            ->selectRaw('audit_error, count(*) as total')->groupBy('audit_error')
            ->orderByDesc('total')->limit($limit)->get()
            ->map(fn ($row) => ['reason' => (string) ($row->audit_error ?: 'Unknown error'), 'count' => (int) $row->total])
            ->all();
    }

    /** Hot 60+, warm 40-59, cold under 40. */
    public function scopeScoreTier(Builder $query, ?string $tier): void
    {
        $hot = (int) config('cloyne.outreach.min_score', 60);
        match ($tier) {
            'hot' => $query->where('score', '>=', $hot),
            'warm' => $query->whereBetween('score', [40, $hot - 1]),
            'cold' => $query->where('score', '<', 40),
            default => null,
        };
    }
}
