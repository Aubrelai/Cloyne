<?php

namespace App\Services;

use App\Models\Audit;
use App\Models\Business;
use Illuminate\Support\Str;

/**
 * The AI audit: scan the website, then have Claude judge the business against
 * LaiQ's package rules (config/cloyne.php) and save the result.
 */
class Auditor
{
    public function __construct(
        private WebsiteScanner $scanner,
        private Claude $claude,
    ) {}

    public function audit(Business $business): Audit
    {
        $signals = $this->scanner->scan($business->website);

        $result = $this->claude->callTool(
            model: config('services.anthropic.audit_model'),
            system: $this->systemPrompt(),
            userMessage: $this->businessBrief($business, $signals),
            tool: $this->tool(),
        );

        $packages = array_keys(Business::packages());
        $package = in_array($result['package'] ?? null, $packages, true) ? $result['package'] : null;
        $pitchPackage = in_array($result['pitch_package'] ?? null, $packages, true) ? $result['pitch_package'] : null;
        if ($pitchPackage === $package) {
            $pitchPackage = null;
        }

        $checks = collect($result['checks'] ?? [])
            ->map(fn ($c) => [
                'title' => Str::limit((string) ($c['title'] ?? ''), 80),
                'detail' => Str::limit((string) ($c['detail'] ?? ''), 140),
                'passed' => (bool) ($c['found'] ?? false),
                'points' => max(0, min(30, (int) ($c['points'] ?? 0))),
            ])
            ->filter(fn ($c) => $c['title'] !== '')
            ->values()
            ->all();

        $disqualified = (bool) ($result['disqualified'] ?? false);

        $audit = $business->recordAudit(
            $checks,
            $disqualified ? ($result['disqualify_reason'] ?? 'Not a fit') : ($result['pitch'] ?? null),
            $disqualified ? null : $package,
            $disqualified ? null : $pitchPackage,
        );
        $audit->update(['signals' => $signals, 'model' => config('services.anthropic.audit_model')]);

        // Save the best contact email Claude picked, but only if the scan actually found it.
        $email = strtolower(trim((string) ($result['email'] ?? '')));
        if ($email !== '' && in_array($email, $signals['emails'] ?? [], true)) {
            $business->contacts()->firstOrCreate(
                ['email' => $email],
                ['name' => $result['contact_name'] ?? null, 'email_status' => 'unverified'],
            );
        }

        $business->update([
            'stage' => $disqualified ? 'excluded' : $business->stage,
            'audit_status' => null,
            'audit_error' => null,
        ]);

        return $audit;
    }

    private function systemPrompt(): string
    {
        $packages = collect(Business::packages())
            ->filter(fn ($p) => $p['order'] !== null)
            ->map(fn ($p, $key) => implode("\n", [
                "### {$p['name']} (key: {$key})",
                "Price: {$p['price']}",
                "Fits: {$p['fits']}",
                'Signs a business needs it:',
                ...array_map(fn ($s) => "- {$s}", $p['signs']),
                'Signs it is NOT a fit:',
                ...array_map(fn ($s) => "- {$s}", $p['not_fit']),
            ]))
            ->implode("\n\n");

        $selling = implode(', ', Business::sellingNow());

        return <<<PROMPT
        You audit local businesses for LaiQ Digital, a marketing agency, and decide how good a prospect each one is.

        You get the business's Google Maps details and the results of an automatic scan of its website. Judge only from
        that evidence. Never invent facts (ads, reviews, emails, staff names) that aren't in the data.

        LaiQ's packages, smallest to biggest:

        {$packages}

        Packages on sale right now: {$selling}

        What to return:
        1. checks: 4 to 8 findings that matter for LaiQ's packages. Each has a short neutral title stating the thing
           checked (e.g. "Website", "Mobile-friendly", "Lead or booking form", "Meta Pixel", "Google reviews"), a one-line
           detail with the evidence (e.g. "No viewport tag; layout won't adapt to phones"), found = true if the business
           has it, and points = how much opportunity it creates for LaiQ (0 to 30). Missing things LaiQ sells
           usually earn points; strengths usually earn 0. Signs the business can afford marketing (many reviews,
           multiple locations, already paying for tools or ads) also earn points.
        2. The points should add up to a 0–100 prospect score: 60+ means worth contacting now, 40–59 maybe, under 40 weak.
        3. package: the ONE package the business fits best, using the signs and not-fit lists above.
        4. pitch_package: only if the best fit is not on sale, a smaller on-sale package that ALSO genuinely fits
           (its not-fit list must not apply). Otherwise null.
        5. pitch: one plain sentence a salesperson could open with, built on the business's biggest gap.
        6. email: the best address to contact the owner, chosen ONLY from the emails the scan found (prefer the
           business's own domain and a person or "info"/"contact" over "noreply"/"careers"). null if none.
        7. disqualified: true only if a not-fit rule rules the business out of every package (e.g. permanently closed,
           a national chain, not a local business). Give the reason.

        Notes on the scan: "Meta Pixel" detection means the site has Facebook tracking, which usually means it runs or
        ran Meta ads; we cannot see the Meta Ad Library yet, so say "likely" rather than claiming active ads.
        A missing viewport tag strongly suggests the site isn't mobile-friendly. A copyright year 3+ years old suggests
        a dated site. If the site didn't load, treat it like a broken website.
        PROMPT;
    }

    private function businessBrief(Business $business, array $signals): string
    {
        $google = array_filter([
            'name' => $business->name,
            'searched as' => $business->category,
            'google category' => $business->google_category,
            'location' => $business->location() ?: null,
            'phone' => $business->phone,
            'website on Google' => $business->website ?? 'none',
            'rating' => $business->rating ? (string) $business->rating : null,
            'review count' => $business->review_count,
        ], fn ($v) => ! is_null($v));

        return "Business (from Google Maps):\n" . json_encode($google, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            . "\n\nWebsite scan:\n" . json_encode($signals, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            . "\n\nAudit this business with the record_audit tool.";
    }

    private function tool(): array
    {
        $keys = array_values(array_keys(array_filter(Business::packages(), fn ($p) => $p['order'] !== null)));

        return [
            'name' => 'record_audit',
            'description' => 'Record the audit of one business.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'checks' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'title' => ['type' => 'string'],
                                'detail' => ['type' => 'string'],
                                'found' => ['type' => 'boolean'],
                                'points' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 30],
                            ],
                            'required' => ['title', 'detail', 'found', 'points'],
                        ],
                    ],
                    'package' => ['type' => ['string', 'null'], 'enum' => [...$keys, null]],
                    'pitch_package' => ['type' => ['string', 'null'], 'enum' => [...$keys, null]],
                    'pitch' => ['type' => 'string'],
                    'email' => ['type' => ['string', 'null']],
                    'contact_name' => ['type' => ['string', 'null']],
                    'disqualified' => ['type' => 'boolean'],
                    'disqualify_reason' => ['type' => ['string', 'null']],
                ],
                'required' => ['checks', 'package', 'pitch_package', 'pitch', 'email', 'disqualified'],
            ],
        ];
    }
}
